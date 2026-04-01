# phpdot/routing

High-performance segment-trie router for PHP. PSR-7/15/17 compliant.

## Install

```bash
composer require phpdot/routing
```

## Quick Start

```php
use PHPdot\Routing\Router;

$router = new Router($container, $responseFactory);

$router->get('/users', [UserController::class, 'index']);
$router->get('/users/{id:int}', [UserController::class, 'show']);
$router->post('/users', [UserController::class, 'store']);

$response = $router->handle($request);
```

One class. Three lines of route registration. One line of dispatch.

---

## Architecture

### Request/Response Lifecycle

```
                          BOOT (once per worker)
 ┌─────────────────────────────────────────────────────────────┐
 │                                                             │
 │   Register routes (fluent API)                              │
 │      $router->get('/users/{id:int}', ...)                   │
 │      $router->group('/api', function () { ... })            │
 │                        │                                    │
 │                        ▼                                    │
 │              RouteCollection                                │
 │           (flat list of Route objects)                       │
 │                        │                                    │
 │                        ▼                                    │
 │             RouteCompiler::compile()                         │
 │                        │                                    │
 │                        ▼                                    │
 │                   TrieNode tree                              │
 │            (indexed by path segments)                        │
 │                        │                                    │
 │                        ▼                                    │
 │                   TrieMatcher                                │
 │              (ready to match requests)                       │
 │                                                             │
 └─────────────────────────────────────────────────────────────┘

                      REQUEST (per coroutine)
 ┌─────────────────────────────────────────────────────────────┐
 │                                                             │
 │   ServerRequestInterface                                    │
 │      GET /users/42                                          │
 │                        │                                    │
 │                        ▼                                    │
 │              Router::handle($request)                        │
 │                        │                                    │
 │                        ▼                                    │
 │              Path::segments('/users/42')                     │
 │              → ['users', '42']                               │
 │                        │                                    │
 │                        ▼                                    │
 │           TrieMatcher::match('GET', segments)                │
 │                        │                                    │
 │              ┌─────────┼──────────┐                         │
 │              ▼         ▼          ▼                         │
 │          RouteMatch  405       null                         │
 │              │     MethodNot   (not found)                   │
 │              │     Allowed        │                         │
 │              │         │          ▼                         │
 │              │         │     fallback()                      │
 │              │         │     or 404                          │
 │              │         ▼                                    │
 │              │    Response 405                               │
 │              │    + Allow header                             │
 │              ▼                                              │
 │     Middleware Pipeline                                      │
 │              │                                              │
 │     ┌────────┼────────┐                                     │
 │     ▼        ▼        ▼                                     │
 │   MW 1 → MW 2 → ... → Route Handler                        │
 │     │        │        │       │                             │
 │     │        │        │       ▼                             │
 │     │        │        │   Controller::show($request, 42)    │
 │     │        │        │       │                             │
 │     ◄────────◄────────◄───────┘                             │
 │              │                                              │
 │              ▼                                              │
 │       ResponseInterface                                     │
 │                                                             │
 └─────────────────────────────────────────────────────────────┘
```

### Trie Matching

Routes are compiled into a segment trie at boot. Matching walks one node per URL segment — O(depth), not O(routes).

```
Routes:
  GET  /users
  GET  /users/{id:int}
  GET  /users/{id:int}/posts
  POST /users/{id:int}/posts
  GET  /api/v1/health
  GET  /docs/{path:*}

Trie:
  root
  ├── "users" ────────────────── [GET → Route#1]
  │   └── {id:int} ──────────── [GET → Route#2]
  │       └── "posts" ────────── [GET → Route#3, POST → Route#4]
  │
  ├── "api"
  │   └── "v1"
  │       └── "health" ────────── [GET → Route#5]
  │
  └── "docs"
      └── {path:*} ────────────── [GET → Route#6]
```

Matching `GET /users/42/posts`:

```
Step 1: root → "users"   (hash lookup)
Step 2: "users" → "42"   (regex: [0-9]+ matches)
Step 3: {id} → "posts"   (hash lookup)
Step 4: leaf has GET?     → Route#3, params: {id: 42}
```

3 lookups. Same speed whether you have 10 routes or 1,000.

### Middleware Pipeline (PSR-15)

Middleware wraps the handler inside-out. Each middleware can modify the request before and the response after.

```
Request
  │
  ▼
┌──────────────────────────────────────┐
│ Middleware 1                         │
│   before: log request                │
│   ┌──────────────────────────────┐   │
│   │ Middleware 2                 │   │
│   │   before: check auth         │   │
│   │   ┌──────────────────────┐   │   │
│   │   │ Route Handler        │   │   │
│   │   │   return Response    │   │   │
│   │   └──────────────────────┘   │   │
│   │   after: add CORS headers    │   │
│   └──────────────────────────────┘   │
│   after: log response                │
└──────────────────────────────────────┘
  │
  ▼
Response
```

Middleware can short-circuit by returning a response without calling `$handler->handle()`.

---

## Package Structure

```
src/
├── Router.php                      Main entry point
│
├── Route/
│   ├── Route.php                   Immutable route definition
│   ├── RouteCollection.php         Flat list of all routes
│   ├── RouteGroup.php              Fluent group builder
│   └── RouteScope.php              Reusable preset bundle
│
├── Compiler/
│   ├── RouteCompiler.php           RouteCollection → TrieNode tree
│   └── PatternRegistry.php         Named regex patterns
│
├── Matcher/
│   ├── MatcherInterface.php        Contract for matchers
│   ├── TrieMatcher.php             Walks compiled trie
│   ├── TrieNode.php                Trie node structure
│   ├── RouteMatch.php              Successful match result
│   └── MethodNotAllowed.php        405 result with allowed methods
│
├── Generator/
│   └── UrlGenerator.php            Named route → URL reversal
│
├── Contracts/
│   ├── ControllerInterface.php     Marker for controller classes
│   └── RouteRegistrarInterface.php Contract for route registration
│
├── Traits/
│   └── HttpMethodsTrait.php        get(), post(), put(), etc.
│
└── Utils/
    └── Path.php                    URL path utilities
```

---

## Route Registration

### Basic Routes

```php
$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->put('/users/{id:int}', [UserController::class, 'update']);
$router->patch('/users/{id:int}', [UserController::class, 'update']);
$router->delete('/users/{id:int}', [UserController::class, 'destroy']);
```

### Closure Handlers

```php
$router->get('/health', function (ServerRequestInterface $request): ResponseInterface {
    return new Response(200, [], json_encode(['status' => 'ok']));
});
```

### String Handlers

```php
$router->get('/users', 'App\Controllers\UserController@index');
```

### Route Parameters

```php
$router->get('/users/{id:int}', ...);           // integer
$router->get('/posts/{slug:slug}', ...);         // slug (a-z, 0-9, hyphens)
$router->get('/files/{name}', ...);              // any (no constraint)
$router->get('/items/{uuid:uuid4}', ...);        // UUID v4
$router->get('/docs/{id:mongo_id}', ...);        // MongoDB ObjectId
```

### Optional Parameters

```php
$router->get('/posts/{page:int?}', function (ServerRequestInterface $req, int $page = 1): ResponseInterface {
    // GET /posts     → page = 1
    // GET /posts/3   → page = 3
});
```

Optional works at any position — beginning, middle, or end:

```php
$router->get('/{lang:locale?}/users', ...);
// GET /users      → lang not set
// GET /en/users   → lang = "en"
// GET /ar/users   → lang = "ar"
```

### Wildcard (Catch-All)

```php
$router->get('/docs/{path:*}', function (ServerRequestInterface $req, string $path): ResponseInterface {
    // GET /docs/guide/install → path = "guide/install"
});
```

### Route Naming

```php
$router->get('/users/{id:int}', [UserController::class, 'show'])->name('users.show');

// Generate URL
$router->url('users.show', ['id' => 42]);                        // /users/42
$router->url('users.show', ['id' => 42], ['tab' => 'posts']);    // /users/42?tab=posts
```

### Where Constraints

```php
$router->get('/items/{code}', [ItemController::class, 'show'])
    ->where('code', 'slug');
```

### Custom Patterns

```php
$router->addPattern('short_id', '[a-zA-Z0-9]{8}');
$router->get('/links/{code:short_id}', [LinkController::class, 'redirect']);
```

---

## Groups

```php
$router->group('/api/v1', function (RouteGroup $group): void {
    $group->get('/users', [UserController::class, 'index']);
    $group->get('/users/{id:int}', [UserController::class, 'show']);
    $group->post('/users', [UserController::class, 'store']);

    $group->group('/admin', function (RouteGroup $admin): void {
        $admin->get('/stats', [StatsController::class, 'index']);
    });
})->middleware(AuthMiddleware::class);
```

Groups accumulate prefixes and middleware. Nested groups inherit from their parent.

---

## Middleware

Middleware implements PSR-15 `MiddlewareInterface`:

```php
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $request->getHeaderLine('Authorization');

        if ($token === '') {
            return new Response(401, [], 'Unauthorized');
        }

        return $handler->handle($request);
    }
}
```

### Global Middleware

```php
$router->middleware(CorsMiddleware::class);
$router->middleware(AuthMiddleware::class);
```

Runs in registration order for every matched route.

### Route Middleware

```php
$router->get('/admin/stats', [StatsController::class, 'index'])
    ->middleware(AdminMiddleware::class);
```

### Closure Middleware

```php
$router->middleware(function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $response = $handler->handle($request);
    return $response->withHeader('X-Request-Id', uniqid());
});
```

---

## Scopes

Reusable preset bundles of middleware and hosts:

```php
$scope = new RouteScope('api');
$scope->middleware(AuthMiddleware::class);
$scope->middleware(RateLimitMiddleware::class);
$scope->host('api.example.com');

$router->addScope($scope);

$router->get('/data', [DataController::class, 'index'])
    ->scope($router->getScope('api'));
```

---

## Host Routing

```php
$router->get('/dashboard', [DashboardController::class, 'index'])
    ->host('admin.example.com');
```

---

## Fallback Handler

```php
$router->fallback(function (ServerRequestInterface $request): ResponseInterface {
    return new Response(404, [], json_encode(['error' => 'Not Found']));
});
```

---

## Controllers

Controllers must implement `ControllerInterface`:

```php
use PHPdot\Routing\Contracts\ControllerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserController implements ControllerInterface
{
    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        return new Response(200, [], json_encode(['id' => $id]));
    }
}
```

Route parameters are passed as method arguments. The request also carries them as attributes:

```php
$request->getAttribute('id');           // 42
$request->getAttribute('_route');       // Route object
$request->getAttribute('_route_params'); // ['id' => 42]
```

---

## Path Utilities

```php
use PHPdot\Routing\Utils\Path;

Path::segments('/users/42/posts');  // ['users', '42', 'posts']
Path::build(['users', '42']);       // /users/42
Path::first('/en/users');           // 'en'
Path::shift('/en/users/42');        // /users/42
```

---

## PSR Standards

| PSR | Interface | Usage |
|-----|-----------|-------|
| PSR-7 | `ServerRequestInterface` | Request input for matching and dispatch |
| PSR-7 | `ResponseInterface` | Handler and middleware return type |
| PSR-11 | `ContainerInterface` | Resolves controllers and middleware |
| PSR-15 | `RequestHandlerInterface` | Router implements this — `$router->handle($request)` |
| PSR-15 | `MiddlewareInterface` | Standard middleware contract |
| PSR-17 | `ResponseFactoryInterface` | Creates 404/405 responses |

---

## Performance

Compilation happens once at boot. Matching is constant-time regardless of route count.

| Scenario | Latency | Throughput |
|----------|---------|------------|
| Static routes | ~0.6 µs | 1.6M matches/sec |
| Dynamic routes | ~0.85 µs | 1.2M matches/sec |
| Worst case (1000 routes) | ~0.78 µs | 1.3M matches/sec |
| 404 not found | ~0.34 µs | 2.9M matches/sec |
| Compilation (1000 routes) | ~1.3 ms | once at boot |

---

## Development

```bash
composer test        # Run tests
composer analyse     # PHPStan level 10
composer cs-fix      # Fix code style
composer cs-check    # Check code style (dry run)
composer check       # Run all three
```

## License

MIT

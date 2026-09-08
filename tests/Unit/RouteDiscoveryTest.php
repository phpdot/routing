<?php

declare(strict_types=1);

/**
 * The discovery invariants whose failure is silent in production.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Tests\Unit;

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Routing\Contract\MountVerifierInterface;
use PHPdot\Routing\Contract\RouteProviderInterface;
use PHPdot\Routing\Exception\RoutingException;
use PHPdot\Routing\RouteDiscovery;
use PHPdot\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class RouteDiscoveryTest extends TestCase
{
    private const string PROVIDERS = __DIR__ . '/../Stubs/Providers';
    private const string NO_PROVIDERS = __DIR__ . '/../Stubs/Empty_';
    private const string MORE = __DIR__ . '/../Stubs/More';

    #[Test]
    public function itDiscoversProvidersByInterfaceAndMountsThem(): void
    {
        $router = $this->mount([self::PROVIDERS], skip: ['DuplicateNameRoutes']);

        $patterns = array_column($router->list(), 'pattern');

        self::assertContains('/alpha', $patterns);
        self::assertContains('/zulu', $patterns);
    }

    #[Test]
    public function itMountsByDescendingPriorityBeforeClassName(): void
    {
        $router = $this->mount([self::PROVIDERS], skip: ['DuplicateNameRoutes']);

        $patterns = array_column($router->list(), 'pattern');

        /**
         * Zulu sorts last by name and first by priority. Asserting the priority wins is the whole
         * point — scanner order is filesystem order and must never decide this.
         */
        self::assertSame(['/zulu', '/alpha'], $patterns);
    }

    #[Test]
    public function itRecordsWhichProviderDeclaredWhichRoute(): void
    {
        /**
         * A provider sees only its own routes and the Router remembers nothing about who added what,
         * so this mapping exists nowhere else — and it is what lets routing:list answer the question the.
         */
        $container = $this->container(['DuplicateNameRoutes']);
        $router = new Router($container, new ResponseFactory());
        $discovery = new RouteDiscovery(container: $container);

        $discovery->mount($router, [self::PROVIDERS]);

        $provenance = $discovery->provenance();

        self::assertArrayHasKey('GET /alpha', $provenance);
        self::assertArrayHasKey('GET /zulu', $provenance);
        self::assertStringEndsWith('AlphaRoutes', $provenance['GET /alpha']);
        self::assertStringEndsWith('ZuluRoutes', $provenance['GET /zulu']);
    }

    #[Test]
    public function itLeavesRoutesAddedOutsideDiscoveryUnattributed(): void
    {
        /**
         * A route added by hand in DI has no provider. routing:list marks it, because it is exactly the
         * route no provider-level rule was ever written about.
         */
        $container = $this->container(['DuplicateNameRoutes']);
        $router = new Router($container, new ResponseFactory());
        $router->addRoute('GET', '/by-hand', static fn(): string => 'x')->name('by.hand');

        $discovery = new RouteDiscovery(container: $container);
        $discovery->mount($router, [self::PROVIDERS]);

        self::assertArrayNotHasKey('GET /by-hand', $discovery->provenance());
    }

    #[Test]
    public function itRefusesToMountWhenNoProviderExists(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessageMatches('/answers 404 to everything/');

        $this->mount([self::NO_PROVIDERS]);
    }

    #[Test]
    public function itMountsNothingWhenEmptinessIsDeclared(): void
    {
        $router = $this->mount([self::NO_PROVIDERS], allowEmpty: true);

        self::assertSame([], $router->list());
    }

    #[Test]
    public function itRefusesADirectoryThatDoesNotExist(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessageMatches('/No route provider directory exists/');

        $this->mount([__DIR__ . '/does-not-exist']);
    }

    #[Test]
    public function itRunsVerifiersAndLetsThemRefuseTheBoot(): void
    {
        $this->expectException(RoutingException::class);
        $this->expectExceptionMessageMatches('/declared more than once: alpha/');

        $this->mount([self::PROVIDERS], verifiers: [$this->duplicateNameVerifier()]);
    }

    #[Test]
    public function itLeavesCompilationToTheCaller(): void
    {
        /**
         * Compilation is Router lifecycle, and match() compiles lazily when it has to. If mount()
         * compiled, the matcher would be built after the first mount and a second mount's routes would.
         */
        $container = $this->container(['DuplicateNameRoutes']);
        $router = new Router($container, new ResponseFactory());

        $discovery = new RouteDiscovery(container: $container);
        $discovery->mount($router, [self::PROVIDERS]);
        $discovery->mount($router, [self::MORE]);

        $router->compile();

        self::assertNotNull($router->match('GET', ['beta']), 'the second mount was invisible');
        self::assertNotNull($router->match('GET', ['alpha']), 'the first mount was lost');
    }

    /**
     * @param list<string> $directories
     * @param list<string> $skip Provider short names to withhold from the container.
     * @param list<\NET5\Platform\Routing\MountVerifier> $verifiers
     */
    private function duplicateNameVerifier(): MountVerifierInterface
    {
        return new class implements MountVerifierInterface {
            public function verify(Router $router): void
            {
                $seen = [];

                foreach ($router->list() as $route) {
                    $name = $route['name'];

                    if ($name !== null && isset($seen[$name])) {
                        throw new RoutingException("declared more than once: {$name}");
                    }

                    $seen[$name] = true;
                }
            }
        };
    }

    private function mount(
        array $directories,
        array $skip = [],
        array $verifiers = [],
        bool $allowEmpty = false,
    ): Router {
        $container = $this->container($skip);
        $router = new Router($container, new ResponseFactory());

        new RouteDiscovery(
            container: $container,
            verifiers: $verifiers,
            allowEmpty: $allowEmpty,
        )->mount($router, $directories);

        return $router;
    }

    /**
     * @param list<string> $skip
     */
    private function container(array $skip): ContainerInterface
    {
        return new class ($skip) implements ContainerInterface {
            /**
             * @param list<string> $skip
             */
            public function __construct(private readonly array $skip) {}

            public function get(string $id): object
            {
                foreach ($this->skip as $name) {
                    if (str_ends_with($id, '\\' . $name)) {
                        return new class implements RouteProviderInterface {
                            public function routes(Router $router): void {}
                        };
                    }
                }

                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }
        };
    }
}

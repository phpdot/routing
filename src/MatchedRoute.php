<?php

declare(strict_types=1);

/**
 * Matched Route
 *
 * The route matched for the *current* request — read off the request's
 * attributes, never off Router instance state.
 *
 * The Router is a shared singleton: under Swoole, many coroutines dispatch
 * through one Router, so any per-request data stored on the Router itself
 * races — one coroutine overwrites another's match while it is suspended
 * inside the middleware/handler pipeline. The matched route lives instead
 * on the immutable per-request object, which is coroutine-local by
 * construction. This class is the typed accessor over those attributes.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing;

use PHPdot\Routing\Route\Route;
use Psr\Http\Message\ServerRequestInterface;

final readonly class MatchedRoute
{
    /**
     * @param Route $route The matched route definition
     * @param array<string, mixed> $parameters Parameters extracted from the request URI
     */
    public function __construct(
        public Route $route,
        public array $parameters,
    ) {}

    /**
     * Read the matched route off the request, or null if none is attached.
     *
     * Coroutine-safe: the request is per-coroutine, so each request observes
     * its own match no matter how many coroutines dispatch through the shared
     * Router in parallel.
     *
     * @param ServerRequestInterface $request
     *
     * @return self|null
     */
    public static function from(ServerRequestInterface $request): self|null
    {
        $route = $request->getAttribute(Router::ROUTE_ATTRIBUTE);

        if (!$route instanceof Route) {
            return null;
        }

        /** @var array<string, mixed> $parameters */
        $parameters = $request->getAttribute(Router::ROUTE_PARAMS_ATTRIBUTE) ?? [];

        return new self($route, $parameters);
    }

    /**
     * The matched route's name, if one was assigned.
     *
     * @return string|null
     */
    public function getName(): string|null
    {
        return $this->route->getName();
    }

    /**
     * The matched route's pattern (without a leading slash).
     *
     * @return string
     */
    public function getPattern(): string
    {
        return $this->route->getPattern();
    }

    /**
     * The HTTP methods the matched route responds to.
     *
     * @return array<string>
     */
    public function getMethods(): array
    {
        return $this->route->getMethods();
    }
}

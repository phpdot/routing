<?php

declare(strict_types=1);

/**
 * Finds route providers by interface, mounts them in declared order, verifies the result.
 *
 * Does not compile: match() compiles lazily, so compiling here would stale a second mount.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing;

use PHPdot\Attribute\Registry;
use PHPdot\Attribute\Scanner;
use PHPdot\Routing\Attribute\Routes;
use PHPdot\Routing\Contract\MountVerifierInterface;
use PHPdot\Routing\Contract\RouteProviderInterface;
use PHPdot\Routing\Exception\RoutingException;
use Psr\Container\ContainerInterface;

final class RouteDiscovery
{
    /**
     * @var array<string, class-string>
     */
    private array $provenance = [];

    /**
     * @param list<MountVerifierInterface> $verifiers
     * @param bool $allowEmpty Permits a surface with no routes, which otherwise refuses the boot.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Scanner $scanner = new Scanner(),
        private readonly array $verifiers = [],
        private readonly bool $allowEmpty = false,
    ) {}

    /**
     * Which provider declared which route, keyed "METHOD /pattern".
     *
     * A provider sees only its own routes and the Router records no origin, so this is the only source.
     *
     * @return array<string, class-string>
     */
    public function provenance(): array
    {
        return $this->provenance;
    }

    /**
     * Mounts every discovered provider onto the router.
     *
     * The scan is unfiltered because findImplementing() reads an interface list a filtered scan omits.
     *
     * @param list<string> $directories
     *
     * @throws RoutingException When no directory exists, no provider is found, or a class fails the contract.
     */
    public function mount(Router $router, array $directories): void
    {
        $paths = array_values(array_filter($directories, is_dir(...)));

        if ($paths === []) {
            throw new RoutingException(
                'No route provider directory exists. Looked in: ' . implode(', ', $directories),
            );
        }

        $registry = $this->scanner->scan($paths);
        $providers = $this->order($registry);

        if ($providers === [] && !$this->allowEmpty) {
            throw new RoutingException(sprintf(
                'No %s was found in: %s. A server with no routes answers 404 to everything; '
                . 'pass allowEmpty if that is intended.',
                RouteProviderInterface::class,
                implode(', ', $paths),
            ));
        }

        foreach ($providers as $class) {
            $provider = $this->container->get($class);

            if (!$provider instanceof RouteProviderInterface) {
                throw new RoutingException(
                    "'{$class}' was discovered as a route provider but does not implement "
                    . RouteProviderInterface::class . '.',
                );
            }

            $before = $this->surface($router);
            $provider->routes($router);

            foreach (array_diff($this->surface($router), $before) as $route) {
                $this->provenance[$route] = $class;
            }
        }

        foreach ($this->verifiers as $verifier) {
            $verifier->verify($router);
        }
    }

    /**
     * The mounted surface as "METHOD /pattern" keys, for diffing one provider against the next.
     *
     * @return list<string>
     */
    private function surface(Router $router): array
    {
        return array_map(
            static fn(array $route): string => implode('|', $route['methods']) . ' ' . $route['pattern'],
            $router->list(),
        );
    }

    /**
     * Providers by descending priority, then class name, never by scan order.
     *
     * @return list<class-string>
     */
    private function order(Registry $registry): array
    {
        $priorities = [];

        foreach ($registry->findByAttribute(Routes::class) as $result) {
            if ($result->instance instanceof Routes) {
                $priorities[$result->class] = $result->instance->priority;
            }
        }

        $classes = array_values(array_filter(
            $registry->findImplementing(RouteProviderInterface::class),
            static fn(string $class): bool => class_exists($class),
        ));

        usort($classes, static fn(string $a, string $b): int
            => [$priorities[$b] ?? 0, $a] <=> [$priorities[$a] ?? 0, $b]);

        return $classes;
    }
}

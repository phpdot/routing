<?php

declare(strict_types=1);

/**
 * Shows the registered scopes in full: each bundle's path prefix, hosts, and
 * middleware, the routes carrying it, and the routes left outside every
 * scope — the surface `routing:list` summarizes into one column, unfolded.
 *
 * @internal
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Cli;

use PHPdot\Console\Command;
use PHPdot\Routing\Router;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'routing:scopes', description: 'Show the registered scopes with their routes.')]
final class ScopesCommand extends Command
{
    /**
     * Nothing here touches a pool, so a Swoole scheduler would only add latency.
     */
    protected bool $coroutine = false;

    public function __construct(
        private readonly Router $router,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('scope', null, InputOption::VALUE_REQUIRED, 'Only the scope with this name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scopes = $this->router->getScopes();
        $filter = $input->getOption('scope');
        $filter = is_string($filter) && $filter !== '' ? $filter : null;

        if ($filter !== null && !isset($scopes[$filter])) {
            $this->warning($output, "No scope named '{$filter}' is registered.");

            return Command::FAILURE;
        }

        if ($scopes === []) {
            $this->warning($output, 'No scopes are registered.');

            return Command::SUCCESS;
        }

        $selected = $filter !== null ? [$filter => $scopes[$filter]] : $scopes;
        $routes = $this->router->list();

        $this->table($output, array_map(
            fn(string $name, $scope): array => [
                'Name' => $name,
                'Path' => $scope->getPath() ?? "\u{2014}",
                'Hosts' => $this->joined($scope->getHosts()),
                'Middleware' => (string) count($scope->getMiddlewares()),
                'Routes' => (string) count($this->carrying($routes, $name)),
            ],
            array_keys($selected),
            $selected,
        ));

        foreach ($selected as $name => $scope) {
            $this->info($output, "Scope [{$name}]");

            $this->table($output, [
                ['Property' => 'Path', 'Value' => $scope->getPath() ?? "\u{2014}"],
                ['Property' => 'Hosts', 'Value' => $this->joined($scope->getHosts())],
                ['Property' => 'Middleware', 'Value' => $this->joined(array_map($this->shortName(...), $scope->getMiddlewares()))],
            ]);

            $carrying = $this->carrying($routes, $name);

            if ($carrying === []) {
                $this->warning($output, 'No routes carry this scope.');

                continue;
            }

            $this->table($output, array_map(
                static fn(array $route): array => [
                    'Method' => implode('|', $route['methods']),
                    'Pattern' => $route['pattern'],
                    'Name' => $route['name'] ?? "\u{2014}",
                ],
                $carrying,
            ));
        }

        $unscoped = $filter !== null ? [] : array_values(array_filter(
            $routes,
            static fn(array $route): bool => $route['scope'] === null,
        ));

        if ($unscoped !== []) {
            $this->info($output, sprintf(
                '%d route%s no scope.',
                count($unscoped),
                count($unscoped) === 1 ? ' carries' : 's carry',
            ));

            $this->table($output, array_map(
                static fn(array $route): array => [
                    'Method' => implode('|', $route['methods']),
                    'Pattern' => $route['pattern'],
                    'Name' => $route['name'] ?? "\u{2014}",
                ],
                $unscoped,
            ));
        }

        return Command::SUCCESS;
    }

    /**
     * The routes carrying the named scope.
     *
     * @param list<array{methods: array<string>, pattern: string, name: string|null, scope: string|null}> $routes The assembled surface
     * @param string $name Scope name
     *
     * @return list<array{methods: array<string>, pattern: string, name: string|null, scope: string|null}>
     */
    private function carrying(array $routes, string $name): array
    {
        return array_values(array_filter(
            $routes,
            static fn(array $route): bool => $route['scope'] === $name,
        ));
    }

    /**
     * A display-friendly list — the em dash when empty.
     *
     * @param array<string> $values
     *
     * @return string
     */
    private function joined(array $values): string
    {
        return $values === [] ? "\u{2014}" : implode(', ', $values);
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}

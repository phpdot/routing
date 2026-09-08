<?php

declare(strict_types=1);

/**
 * Shows the assembled route surface, each route attributed to the provider that declared it.
 *
 * @internal
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Routing\Cli;

use PHPdot\Console\Command;
use PHPdot\Routing\RouteDiscovery;
use PHPdot\Routing\Router;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'routing:list', description: 'Show the mounted route surface.')]
final class ListCommand extends Command
{
    /**
     * Nothing here touches a pool, so a Swoole scheduler would only add latency.
     */
    protected bool $coroutine = false;

    public function __construct(
        private readonly Router $router,
        private readonly RouteDiscovery $discovery,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Only routes whose pattern contains this')
            ->addOption('middleware', 'm', InputOption::VALUE_NONE, 'Add a middleware count column');
    }

    /**
     * A null provider means the route was added outside discovery.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $routes = $this->router->list();
        $filter = $input->getOption('path');
        $filter = is_string($filter) && $filter !== '' ? $filter : null;

        if ($filter !== null) {
            $needle = $filter;
            $routes = array_values(array_filter(
                $routes,
                static fn(array $route): bool => str_contains($route['pattern'], $needle),
            ));
        }

        if ($routes === []) {
            $this->warning($output, $filter === null ? 'No routes are mounted.' : "No route matches '{$filter}'.");

            return Command::SUCCESS;
        }

        $provenance = $this->discovery->provenance();
        $withMiddleware = $input->getOption('middleware') === true;
        $rows = [];

        foreach ($routes as $route) {
            $key = implode('|', $route['methods']) . ' ' . $route['pattern'];
            $provider = $provenance[$key] ?? null;

            $row = [
                'Method' => implode('|', $route['methods']),
                'Pattern' => $route['pattern'],
                'Name' => $route['name'] ?? "\u{2014}",
                'Scope' => $route['scope'] ?? "\u{2014}",
                'Handler' => $route['handler'],
                'Provider' => $provider === null ? "\u{2014} (not from a provider)" : $this->shortName($provider),
            ];

            if ($withMiddleware) {
                $row['MW'] = (string) count($route['middlewares']);
            }

            $rows[] = $row;
        }

        $this->table($output, $rows);
        $this->info($output, sprintf('%d route%s mounted.', count($rows), count($rows) === 1 ? '' : 's'));

        return Command::SUCCESS;
    }

    private function shortName(string $class): string
    {
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}

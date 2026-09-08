<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Unit\Cli;

use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Routing\Cli\ScopesCommand;
use PHPdot\Routing\Route\RouteScope;
use PHPdot\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The scopes command unfolds what routing:list compresses into one column:
 * every registered bundle (path, hosts, middleware), the routes carrying
 * each, and the routes left outside every scope.
 */
final class ScopesCommandTest extends TestCase
{
    #[Test]
    public function showsEveryScopeWithFullDetailsAndItsRoutes(): void
    {
        $tester = new CommandTester(new ScopesCommand($this->router()));

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();

        self::assertStringContainsString('Scope [apps]', $display);
        self::assertStringContainsString('/apps', $display);
        self::assertStringContainsString('stdClass', $display, 'middleware renders by short name');
        self::assertStringContainsString('/apps/dashboard', $display);
        self::assertStringContainsString('app.dashboard', $display);
        self::assertStringContainsString('Scope [api]', $display);
        self::assertStringContainsString('api.example.test', $display);
    }

    #[Test]
    public function listsTheRoutesLeftOutsideEveryScope(): void
    {
        $tester = new CommandTester(new ScopesCommand($this->router()));

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();

        self::assertStringContainsString('1 route carries no scope', $display);
        self::assertStringContainsString('/login', $display);
    }

    #[Test]
    public function aScopeFilterLimitsTheOutputToThatScope(): void
    {
        $tester = new CommandTester(new ScopesCommand($this->router()));

        self::assertSame(Command::SUCCESS, $tester->execute(['--scope' => 'api']));

        $display = $tester->getDisplay();

        self::assertStringContainsString('Scope [api]', $display);
        self::assertStringNotContainsString('Scope [apps]', $display);
        self::assertStringNotContainsString('carry no scope', $display);
    }

    #[Test]
    public function anUnknownScopeFilterFails(): void
    {
        $tester = new CommandTester(new ScopesCommand($this->router()));

        self::assertSame(Command::FAILURE, $tester->execute(['--scope' => 'ghost']));

        self::assertStringContainsString("No scope named 'ghost'", $tester->getDisplay());
    }

    #[Test]
    public function aRouterWithoutScopesSaysSoAndSucceeds(): void
    {
        $router = $this->bareRouter();
        $router->addRoute('GET', '/login', static fn(): string => 'x');
        $tester = new CommandTester(new ScopesCommand($router));

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        self::assertStringContainsString('No scopes are registered.', $tester->getDisplay());
    }

    /**
     * A router with two scopes, routes inside each, and one route outside.
     */
    private function router(): Router
    {
        $router = $this->bareRouter();

        $router->addScope(
            (new RouteScope('apps'))->path('/apps')->middleware(stdClass::class),
        );
        $router->addScope(
            (new RouteScope('api'))->hosts(['api.example.test']),
        );

        $router->addRoute('GET', '/login', static fn(): string => 'x')->name('anon.login');

        $router->addRoute('GET', '/dashboard', static fn(): string => 'x')
            ->name('app.dashboard')
            ->scope($router->getScope('apps'));

        $router->addRoute('POST', '/feed', static fn(): string => 'x')
            ->name('api.feed')
            ->scope($router->getScope('api'));

        return $router;
    }

    private function bareRouter(): Router
    {
        return new Router(
            new class implements ContainerInterface {
                public function get(string $id): mixed
                {
                    throw new RuntimeException("Nothing to resolve in the scopes-command fixture: {$id}");
                }

                public function has(string $id): bool
                {
                    return false;
                }
            },
            new ResponseFactory(),
        );
    }
}

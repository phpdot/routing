<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Stubs;

use PHPdot\Http\Message\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

final class RequestFactory
{
    public static function create(string $method, string $path, string $host = 'localhost'): ServerRequestInterface
    {
        return new ServerRequest($method, $path, ['Host' => $host]);
    }
}

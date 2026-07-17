<?php

declare(strict_types=1);

namespace PHPdot\Routing\Tests\Stubs;

use PHPdot\Http\Message\Response;
use PHPdot\Routing\Contract\ControllerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class StubController implements ControllerInterface
{
    public function index(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(200, [], 'index');
    }

    public function show(ServerRequestInterface $request, int $id): ResponseInterface
    {
        return new Response(200, [], "show:{$id}");
    }

    public function store(ServerRequestInterface $request): ResponseInterface
    {
        return new Response(201, [], 'created');
    }
}

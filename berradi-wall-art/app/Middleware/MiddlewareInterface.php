<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

interface MiddlewareInterface
{
    /**
     * @param callable(Request):Response $next
     */
    public function handle(Request $request, callable $next, string ...$params): Response;
}

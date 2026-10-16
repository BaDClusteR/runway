<?php

declare(strict_types=1);

namespace Runway\Router\Matcher;

use Runway\Router\DTO\RouteMatchResultDTO;
use Runway\Service\Exception\Route\RouteException;

interface IRouteMatcher {
    /**
     * @param string $path   URL-decoded path, e.g. "/chats/42".
     * @param string $method HTTP method.
     *
     * @throws RouteException If the routes are invalid or conflict with each other.
     */
    public function match(string $path, string $method): RouteMatchResultDTO;
}

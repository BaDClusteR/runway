<?php

declare(strict_types=1);

namespace Runway\Router\Compiler;

use Runway\Router\DTO\CompiledRouteDTO;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\Exception\Route\RouteException;

interface IRouteCompiler {
    /**
     * @throws RouteException If the path template is invalid.
     */
    public function compile(RouteDTO $route): CompiledRouteDTO;
}

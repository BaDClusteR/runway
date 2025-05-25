<?php

declare(strict_types=1);

namespace Runway\Service\Exception\Route;

use Runway\Service\DTO\RouteDTO;
use Throwable;

class RouteDeclaredMoreThanOnce extends RouteException {
    public function __construct(
        RouteDTO   $route,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($route, "$route->name declared more than once", $code, $previous);
    }
}
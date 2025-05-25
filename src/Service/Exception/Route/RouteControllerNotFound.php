<?php

declare(strict_types=1);

namespace Runway\Service\Exception\Route;

use Runway\Service\DTO\RouteDTO;
use Throwable;

class RouteControllerNotFound extends RouteException {
    public function __construct(
        RouteDTO   $route,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($route, "Controller {$route->controller} for the route {$route->name} not found", $code, $previous);
    }
}
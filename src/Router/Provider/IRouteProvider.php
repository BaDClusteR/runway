<?php

declare(strict_types=1);

namespace Runway\Router\Provider;

use Runway\Service\DTO\RouteDTO;
use Runway\Service\Exception\Route\RouteException;

/**
 * A source of routes for the router: the routes.yaml files, the API endpoints, etc. The providers are the services
 * with the "router.route_provider" tag.
 */
interface IRouteProvider {
    /**
     * @return RouteDTO[]
     *
     * @throws RouteException
     */
    public function getRoutes(): array;
}

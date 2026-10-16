<?php

declare(strict_types=1);

namespace Runway\Router\DTO;

use Runway\Service\DTO\RouteDTO;

readonly class RouteMatchDTO {
    /**
     * @param array<string, string> $parameters Placeholder name => its value from the path.
     */
    public function __construct(
        public RouteDTO $route,
        public array    $parameters
    ) {}
}

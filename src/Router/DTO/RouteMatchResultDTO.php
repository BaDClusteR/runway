<?php

declare(strict_types=1);

namespace Runway\Router\DTO;

readonly class RouteMatchResultDTO {
    /**
     * @param RouteMatchDTO|null $match          Null if no route matches both the path and the method.
     * @param string[]           $allowedMethods The methods of the routes that match the path only: if there are any,
     *                                           it's "405 Method Not Allowed" rather than "404 Not Found".
     */
    public function __construct(
        public ?RouteMatchDTO $match,
        public array          $allowedMethods = []
    ) {}
}

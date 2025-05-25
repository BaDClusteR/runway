<?php

declare(strict_types=1);

namespace Runway\Service\DTO;

readonly class RouteDTO {
    public function __construct(
        public string $name,
        public string $path,
        public string $controller,
        public string $method,
        public int    $priority,
        public string $description,
        public string $filePath
    ) {}
}
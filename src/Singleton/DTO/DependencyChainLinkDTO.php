<?php

declare(strict_types=1);

namespace Runway\Singleton\DTO;

readonly class DependencyChainLinkDTO {
    public function __construct(
        public string $serviceName,
        public string $fileName,
        public int    $line,
        public string $method
    ) {}
}
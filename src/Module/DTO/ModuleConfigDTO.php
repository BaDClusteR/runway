<?php

declare(strict_types=1);

namespace Runway\Module\DTO;

readonly class ModuleConfigDTO {
    public function __construct(
        public string $name,
        public string $description,
        public string $version
    ) {}
}
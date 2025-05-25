<?php

declare(strict_types=1);

namespace Runway\Module\DTO;

readonly class ModuleDTO {
    public function __construct(
        public string          $systemName,
        public ModuleConfigDTO $config,
        public bool            $isEnabled,
        public string          $rootPath,
    ) {}
}
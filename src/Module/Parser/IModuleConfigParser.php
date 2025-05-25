<?php

namespace Runway\Module\Parser;

use Runway\Module\DTO\ModuleConfigDTO;

interface IModuleConfigParser {
    public function parseModuleConfig(string $moduleRootPath): ?ModuleConfigDTO;
}
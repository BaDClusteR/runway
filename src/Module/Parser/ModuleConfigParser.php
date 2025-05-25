<?php

declare(strict_types=1);

namespace Runway\Module\Parser;

use Runway\Module\DTO\ModuleConfigDTO;

class ModuleConfigParser implements IModuleConfigParser {
    public function parseModuleConfig(string $moduleRootPath): ?ModuleConfigDTO {
        $filePath = $this->getConfigFilePath($moduleRootPath);

        if (file_exists($filePath) && is_readable($filePath)) {
            $info = yaml_parse_file($filePath);

            if (is_array($info)) {
                return new ModuleConfigDTO(
                    name: (string)($info['name'] ?? ''),
                    description: (string)($info['description'] ?? ''),
                    version: (string)($info['version'] ?? '')
                );
            }
        }

        return null;
    }

    protected function getConfigFilePath(string $moduleRootPath): string {
        return "$moduleRootPath/config.yaml";
    }
}
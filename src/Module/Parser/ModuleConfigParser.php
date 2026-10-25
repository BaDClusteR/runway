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
                    name: $this->getString($info, "name"),
                    description: $this->getString($info, "description"),
                    version: $this->getString($info, "version")
                );
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $info
     */
    protected function getString(array $info, string $key): string {
        $value = $info[$key] ?? "";

        return is_scalar($value) ? (string)$value : "";
    }

    protected function getConfigFilePath(string $moduleRootPath): string {
        return "$moduleRootPath/config.yaml";
    }
}
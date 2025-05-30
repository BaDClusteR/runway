<?php

declare(strict_types=1);

namespace Runway\Service\Provider;

use Runway\Env\Provider\EnvVariablesProvider;

class ConfigDirectoriesProvider implements IConfigDirectoriesProvider {
    public function getDirectories(): array {
        return [
            ...PathsProvider::getInstance()->getConfigDirectories(),
            ...$this->getEnabledModuleDirectories()
        ];
    }

    protected function getEnabledModuleDirectories(): array {
        $dirs = [];
        $moduleRoot = $this->getModuleRoot();

        $envVariablesProvider = new EnvVariablesProvider();
        $moduleNames = array_map(
            static fn(string $moduleSystemName): string => trim($moduleSystemName),
            explode(
                ",",
                $envVariablesProvider->getEnvVariable("MODULES")
            )
        );

        foreach ($moduleNames as $moduleName) {
            $moduleDir = "$moduleRoot/$moduleName/config";

            if (file_exists($moduleDir) && is_dir($moduleDir)) {
                $dirs[] = $moduleDir;
            }
        }

        return $dirs;
    }

    protected function getModuleRoot(): string {
        return PathsProvider::getInstance()->getModulesDirectory();
    }
}
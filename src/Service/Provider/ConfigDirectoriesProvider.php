<?php

declare(strict_types=1);

namespace Runway\Service\Provider;

use Runway\Env\Provider\EnvVariablesProvider;

class ConfigDirectoriesProvider implements IConfigDirectoriesProvider {
    public function getDirectories(): array {
        /** @noinspection PhpUndefinedConstantInspection */
        return [
            RUNWAY_ROOT . '/config',
            PROJECT_ROOT . '/config',
            ...$this->getEnabledModuleDirectories()
        ];
    }

    protected function getEnabledModuleDirectories(): array {
        $dirs = [];

        $envVariablesProvider = new EnvVariablesProvider();
        $moduleNames = array_map(
            static fn(string $moduleSystemName): string => trim($moduleSystemName),
            explode(
                ",",
                $envVariablesProvider->getEnvVariable("MODULES")
            )
        );

        foreach ($moduleNames as $moduleName) {
            $moduleDir = MODULE_ROOT . "/$moduleName/config";

            if (file_exists($moduleDir) && is_dir($moduleDir)) {
                $dirs[] = $moduleDir;
            }
        }

        return $dirs;
    }
}
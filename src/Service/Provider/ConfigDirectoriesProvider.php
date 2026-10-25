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

    /**
     * @return string[]
     */
    protected function getEnabledModuleDirectories(): array {
        $dirs = [];
        $moduleRoot = $this->getModuleRoot();

        // No modules root: a module name would make a path from the root of the file system ("/config").
        if ($moduleRoot === '') {
            return [];
        }

        foreach ($this->getEnabledModuleNames() as $moduleName) {
            $moduleDir = "$moduleRoot/$moduleName/config";

            if (is_dir($moduleDir)) {
                $dirs[] = $moduleDir;
            }
        }

        return $dirs;
    }

    /**
     * @return string[] The names of MODULES (comma-separated): the folder names only, a path ("../other") is skipped.
     */
    protected function getEnabledModuleNames(): array {
        $modules = $this->getModulesVariable();

        return array_values(array_filter(
            array_map('trim', explode(",", is_scalar($modules) ? (string)$modules : '')),
            static fn(string $moduleName): bool => (bool)preg_match('/^[A-Za-z0-9_-]+$/', $moduleName)
        ));
    }

    protected function getModulesVariable(): mixed {
        return new EnvVariablesProvider()->getEnvVariable("MODULES");
    }

    protected function getModuleRoot(): string {
        return PathsProvider::getInstance()->getModulesDirectory();
    }
}

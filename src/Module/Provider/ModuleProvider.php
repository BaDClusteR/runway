<?php

declare(strict_types=1);

namespace Runway\Module\Provider;

use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Module\DTO\ModuleDTO;
use Runway\Module\Parser\IModuleConfigParser;

class ModuleProvider implements IModuleProvider {
    /**
     * @var string[]
     */
    protected array $enabledModuleNames = [];

    public function __construct(
        protected IEnvVariablesProvider $envVariablesProvider,
        protected IModuleConfigParser   $configParser
    ) {}

    /**
     * @return ModuleDTO[]
     */
    public function getEnabledModules(): array {
        return array_filter(
            $this->getAllModules(),
            static fn(ModuleDTO $module): bool => $module->isEnabled
        );
    }

    /**
     * @return ModuleDTO[]
     */
    public function getAllModules(): array {
        $modules = [];
        $this->enabledModuleNames = $this->getEnabledModuleNamesFromEnvVar();
        $modulesRoot = $this->getModulesRoot();

        foreach (scandir($modulesRoot) as $moduleDirRecord) {
            if (
                !in_array($moduleDirRecord, ['.', '..'], true)
                && is_dir("$modulesRoot/$moduleDirRecord")
            ) {
                $modules[] = $this->getModuleDTOByFolderName($moduleDirRecord);
            }
        }

        return array_filter($modules);
    }

    public function getModule(string $systemName): ?ModuleDTO {
        return array_find(
            $this->getAllModules(),
            static fn($module): bool => $module->systemName === $systemName
        );
    }

    public function isModuleEnabled(string $moduleName): bool {
        return (bool)$this->getModule($moduleName)?->isEnabled;
    }

    public function isModuleExists(string $moduleName): bool {
        return $this->getModule($moduleName) !== null;
    }

    /**
     * @return string[]
     */
    protected function getEnabledModuleNamesFromEnvVar(): array {
        $rawConfigValue = $this->envVariablesProvider->getEnvVariable("MODULES");

        return array_map(
            static fn(string $moduleName): string => trim($moduleName),
            explode(",", $rawConfigValue),
        );
    }

    protected function getModuleDTOByFolderName(string $moduleFolder): ?ModuleDTO {
        $moduleRoot = $this->getModulesRoot() . '/' . $moduleFolder;

        if ($moduleConfig = $this->configParser->parseModuleConfig($moduleRoot)) {
            return new ModuleDTO(
                systemName: $moduleFolder,
                config: $moduleConfig,
                isEnabled: in_array($moduleFolder, $this->enabledModuleNames, true),
                rootPath: $moduleRoot
            );
        }

        return null;
    }

    protected function getModulesRoot(): string {
        return MODULE_ROOT;
    }
}
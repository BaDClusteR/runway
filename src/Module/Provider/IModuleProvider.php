<?php

namespace Runway\Module\Provider;

use Runway\Module\DTO\ModuleDTO;

interface IModuleProvider {
    /**
     * @return ModuleDTO[]
     */
    public function getEnabledModules(): array;

    /**
     * @return ModuleDTO[]
     */
    public function getAllModules(): array;

    public function getModule(string $systemName): ?ModuleDTO;

    public function isModuleEnabled(string $moduleName): bool;

    public function isModuleExists(string $moduleName): bool;
}
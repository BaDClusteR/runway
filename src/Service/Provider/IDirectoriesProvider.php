<?php

namespace Runway\Service\Provider;

interface IDirectoriesProvider
{
    public function addConfigDirectory(string $directoryPath): void;

    /**
     * @return string[]
     */
    public function getConfigDirectories(): array;

    public function addEnvFilePath(string $directoryPath): void;

    /**
     * @return string[]
     */
    public function getEnvFilePaths(): array;

    public function setModulesDirectory(string $directoryPath): void;

    public function getModulesDirectory(): string;
}
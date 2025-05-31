<?php

namespace Runway\Service\Provider;

use Runway\Singleton;

class PathsProvider extends Singleton implements IPathsProvider
{
    protected static array $configDirectories = [];

    protected static array $envFilePaths = [];

    protected static string $modulesDirectory = "";

    public function addConfigDirectory(string $directoryPath): void
    {
        if (!in_array($directoryPath, self::$configDirectories, true)) {
            static::$configDirectories[] = $directoryPath;
        }
    }

    /**
     * @return string[]
     */
    public function getConfigDirectories(): array
    {
        return static::$configDirectories;
    }

    public function addEnvFilePath(string $directoryPath): void
    {
        if (!in_array($directoryPath, self::$envFilePaths, true)) {
            static::$envFilePaths[] = $directoryPath;
        }
    }

    /**
     * @return string[]
     */
    public function getEnvFilePaths(): array
    {
        return static::$envFilePaths;
    }

    public function setModulesDirectory(string $directoryPath): void
    {
        static::$modulesDirectory = $directoryPath;
    }

    public function getModulesDirectory(): string
    {
        return static::$modulesDirectory;
    }
}
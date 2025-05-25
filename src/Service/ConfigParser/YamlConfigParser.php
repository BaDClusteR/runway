<?php

declare(strict_types=1);

namespace Runway\Service\ConfigParser;

use Runway\Event\Exception\EventException;
use Runway\Service\DataBuilder\IParsedConfigBuilder;
use Runway\Service\DataBuilder\ParsedConfigBuilder;
use Runway\Service\DTO\ParsedConfigDTO;
use Runway\Service\Helper\ParsedConfigHelper;

class YamlConfigParser implements IConfigParser {
    protected ParsedConfigHelper $helper;

    protected IParsedConfigBuilder $dataBuilder;

    public function __construct() {
        $this->helper = new ParsedConfigHelper();
        $this->dataBuilder = new ParsedConfigBuilder();
    }

    /**
     * @throws EventException
     */
    protected function parseFile(string $filePath): ?ParsedConfigDTO {
        $yamlData = yaml_parse_file($filePath);

        if (is_array($yamlData)) {
            $this->dataBuilder->setRawData($yamlData)
                              ->setFilePath($filePath);

            return new ParsedConfigDTO(
                $this->dataBuilder->buildParsedServices(),
                $this->dataBuilder->buildRoutes(),
                $this->dataBuilder->buildParameters()
            );
        }

        return null;
    }

    /**
     * @param string[] $directories
     *
     * @throws EventException
     */
    public function parseDirectories(array $directories): ParsedConfigDTO {
        $configs = [];

        foreach ($directories as $directory) {
            $configs = [
                ...$configs,
                ...$this->parseDirectory($directory)
            ];
        }

        return $this->helper->mergeParsedConfigs(...$configs);
    }

    protected function parseDirectory(string $directory): array {
        $files = scandir($directory);
        $configs = [];

        if (is_array($files)) {
            foreach ($files as $fileName) {
                if (
                    $fileName !== "."
                    && $fileName !== ".."
                ) {
                    $fullPath = "$directory/$fileName";

                    /** @noinspection NotOptimalIfConditionsInspection */
                    if (
                        strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)) === "yaml"
                        && ($fileConfig = $this->parseFile($fullPath))
                    ) {
                        $configs[] = $fileConfig;
                    } elseif (is_dir($fullPath)) {
                        $configs = [
                            ...$configs,
                            ...$this->parseDirectory($fullPath)
                        ];
                    }
                }
            }
        }

        return $configs;
    }
}
<?php

declare(strict_types=1);

namespace Runway\Service\Provider;

use Runway\Event\Exception\EventException;
use Runway\Service\ConfigParser\IConfigParser;
use Runway\Service\ConfigParser\YamlConfigParser;
use Runway\Service\Converter\ConfigConverter;
use Runway\Service\Converter\IConfigConverter;
use Runway\Service\DTO\ConfigDTO;
use Runway\Service\Exception\ServiceException;
use Runway\Service\Helper\ConfigHelper;
use Runway\Service\Helper\ParsedConfigHelper;
use Runway\Singleton;

class ConfigProvider extends Singleton implements IConfigProvider {
    protected static ?ConfigDTO $config = null;

    protected ConfigHelper $configHelper;

    protected ParsedConfigHelper $parsedConfigHelper;

    protected ConfigDirectoriesProvider $directoriesProvider;

    protected IConfigConverter $configConverter;

    public function __construct() {
        $this->initHelpers();
    }

    protected function initHelpers(): void {
        $this->configHelper = new ConfigHelper();
        $this->parsedConfigHelper = new ParsedConfigHelper();
        $this->directoriesProvider = new ConfigDirectoriesProvider();
        $this->configConverter = new ConfigConverter();
    }

    /**
     * @throws ServiceException
     * @throws EventException
     */
    public function getConfig(): ConfigDTO {
        if (static::$config === null) {
            static::$config = $this->doGetConfig();
        }

        return static::$config;
    }

    /**
     * @throws ServiceException
     * @throws EventException
     */
    protected function doGetConfig(): ConfigDTO {
        $directories = $this->directoriesProvider->getDirectories();
        $parsedConfigs = [];

        foreach ($this->getConfigParsersFQN() as $parserFQN) {
            /**
             * @var IConfigParser $configParser
             */
            $configParser = new $parserFQN();

            $parsedConfigs[] = $configParser->parseDirectories($directories);
        }

        return $this->configConverter->convertFromParsedConfig(
            $this->parsedConfigHelper->mergeParsedConfigs(...$parsedConfigs)
        );
    }

    protected function getConfigParsersFQN(): array {
        return [
            YamlConfigParser::class
        ];
    }
}
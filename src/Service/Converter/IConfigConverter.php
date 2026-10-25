<?php

declare(strict_types=1);

namespace Runway\Service\Converter;

use Runway\Service\DTO\ConfigDTO;
use Runway\Service\DTO\ParsedConfigDTO;
use Runway\Service\DTO\ParsedServiceDTO;
use Runway\Service\DTO\ServiceDTO;
use Runway\Service\Exception\ServiceDeclaredMoreThanOnceException;

interface IConfigConverter {
    public function convertFromParsedService(ParsedServiceDTO $parsedServiceDTO): ServiceDTO;

    /**
     * @throws ServiceDeclaredMoreThanOnceException
     */
    public function convertFromParsedConfig(ParsedConfigDTO $parsedConfig): ConfigDTO;
}
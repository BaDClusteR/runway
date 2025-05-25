<?php

declare(strict_types=1);

namespace Runway\Service\Converter;

use Runway\Service\DTO\ConfigDTO;
use Runway\Service\DTO\ParsedConfigDTO;
use Runway\Service\Exception\ServiceDeclaredMoreThanOnceException;

interface IConfigConverter {
    /**
     * @throws ServiceDeclaredMoreThanOnceException
     */
    public function convertFromParsedConfig(ParsedConfigDTO $parsedConfig): ConfigDTO;
}
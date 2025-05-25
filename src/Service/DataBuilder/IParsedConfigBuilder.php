<?php

namespace Runway\Service\DataBuilder;

use Runway\Event\Exception\EventException;
use Runway\Service\DTO\ParameterDTO;
use Runway\Service\DTO\ParsedServiceDTO;
use Runway\Service\DTO\RouteDTO;

interface IParsedConfigBuilder {
    public function setRawData(array $rawData): static;

    public function setFilePath(string $filepath): static;

    /**
     * @return RouteDTO[]
     */
    public function buildRoutes(): array;

    /**
     * @return ParameterDTO[]
     */
    public function buildParameters(): array;

    /**
     * @return ParsedServiceDTO[]
     * @throws EventException
     */
    public function buildParsedServices(): array;
}
<?php

declare(strict_types=1);

namespace Runway\Service\DTO;

class ParsedConfigDTO {
    /**
     * @param ParsedServiceDTO[] $parsedServiceConfig
     * @param RouteDTO[]         $routes
     * @param ParameterDTO[]     $parameters
     */
    public function __construct(
        protected array $parsedServiceConfig = [],
        protected array $routes = [],
        protected array $parameters = []
    ) {}

    /**
     * @return ParsedServiceDTO[]
     */
    public function getParsedServiceConfig(): array {
        return $this->parsedServiceConfig;
    }

    /**
     * @param ParsedServiceDTO[] $parsedServiceConfig
     */
    public function setParsedServiceConfig(array $parsedServiceConfig): static {
        $this->parsedServiceConfig = $parsedServiceConfig;

        return $this;
    }

    public function getRoutes(): array {
        return $this->routes;
    }

    public function setRoutes(array $routes): static {
        $this->routes = $routes;

        return $this;
    }

    /**
     * @return ParameterDTO[]
     */
    public function getParameters(): array {
        return $this->parameters;
    }

    /**
     * @param ParameterDTO[] $parameters
     */
    public function setParameters(array $parameters): static {
        $this->parameters = $parameters;

        return $this;
    }
}
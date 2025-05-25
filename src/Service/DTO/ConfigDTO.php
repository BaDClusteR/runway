<?php

declare(strict_types=1);

namespace Runway\Service\DTO;

class ConfigDTO {
    /**
     * @param ServiceDTO[]   $serviceConfig
     * @param RouteDTO[]     $routeConfig
     * @param ParameterDTO[] $parametersConfig
     */
    public function __construct(
        protected array $serviceConfig = [],
        protected array $routeConfig = [],
        protected array $parametersConfig = []
    ) {}

    /**
     * @return ServiceDTO[]
     */
    public function getServiceConfig(): array {
        return $this->serviceConfig;
    }

    public function getRouteConfig(): array {
        return $this->routeConfig;
    }

    /**
     * @param ServiceDTO[] $serviceConfig
     */
    public function setServiceConfig(array $serviceConfig): static {
        $this->serviceConfig = $serviceConfig;

        return $this;
    }

    /**
     * @param RouteDTO[] $routeConfig
     */
    public function setRouteConfig(array $routeConfig): static {
        $this->routeConfig = $routeConfig;

        return $this;
    }

    /**
     * @return ParameterDTO[]
     */
    public function getParametersConfig(): array {
        return $this->parametersConfig;
    }

    /**
     * @param ParameterDTO[] $parametersConfig
     */
    public function setParametersConfig(array $parametersConfig): static {
        $this->parametersConfig = $parametersConfig;

        return $this;
    }

    public function getParameter(string $name): mixed {
        return $this->parametersConfig[$name]->value ?? null;
    }
}
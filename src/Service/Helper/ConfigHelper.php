<?php

declare(strict_types=1);

namespace Runway\Service\Helper;

use Runway\Service\DTO\ConfigDTO;
use Runway\Service\Exception\ServiceDeclaredMoreThanOnceException;

class ConfigHelper {
    private ConfigDTO $config;

    public function __construct() {
        $this->config = new ConfigDTO();
    }

    /**
     * @throws ServiceDeclaredMoreThanOnceException
     */
    protected function mergeConfig(ConfigDTO $config): void {
        $services = $this->config->getServiceConfig();

        foreach ($config->getServiceConfig() as $service) {
            $serviceName = $service->getName();

            if (array_key_exists($serviceName, $services)) {
                throw new ServiceDeclaredMoreThanOnceException($service, $services[$serviceName]);
            }

            $services[$serviceName] = $service;
        }

        $this->config->setServiceConfig($services);
    }

    /**
     * @throws ServiceDeclaredMoreThanOnceException
     */
    public function mergeConfigs(...$configs): ConfigDTO {
        $this->resetConfig();

        foreach ($configs as $config) {
            $this->mergeConfig($config);
        }

        return $this->config;
    }

    protected function resetConfig(): void {
        unset($this->config);
        $this->config = new ConfigDTO();
    }
}
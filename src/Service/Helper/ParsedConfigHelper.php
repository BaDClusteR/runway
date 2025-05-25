<?php

declare(strict_types=1);

namespace Runway\Service\Helper;

use Runway\Service\Converter\ConfigConverter;
use Runway\Service\Converter\IConfigConverter;
use Runway\Service\DTO\ParsedConfigDTO;
use Runway\Service\Exception\Route\RouteDeclaredMoreThanOnce;
use Runway\Service\Exception\ServiceDeclaredMoreThanOnceException;
use Runway\Service\Exception\ServiceException;

class ParsedConfigHelper {
    private ParsedConfigDTO $config;

    private IConfigConverter $converter;

    public function __construct() {
        $this->config = new ParsedConfigDTO();
        $this->converter = new ConfigConverter();
    }

    /**
     * @throws ServiceDeclaredMoreThanOnceException
     */
    protected function mergeParsedConfig(ParsedConfigDTO $config): void {
        $this->mergeParsedConfigServices($config);
        $this->mergeParsedConfigRoutes($config);
        $this->mergeParameters($config);
    }

    /**
     * @throws ServiceDeclaredMoreThanOnceException
     */
    protected function mergeParsedConfigServices(ParsedConfigDTO $config): void {
        $services = $this->config->getParsedServiceConfig();

        foreach ($config->getParsedServiceConfig() as $service) {
            $serviceName = $service->getName();

            if (array_key_exists($serviceName, $services)) {
                throw new ServiceDeclaredMoreThanOnceException(
                    $this->converter->convertFromParsedService($service),
                    $this->converter->convertFromParsedService($services[$serviceName])
                );
            }

            $services[$serviceName] = $service;
        }

        $this->config->setParsedServiceConfig($services);
    }

    protected function mergeParsedConfigRoutes(ParsedConfigDTO $config): void {
        $routes = $this->config->getRoutes();

        foreach ($config->getRoutes() as $route) {
            if (array_key_exists($route->name, $routes)) {
                throw new RouteDeclaredMoreThanOnce($route);
            }

            $routes[$route->name] = $route;
        }

        $this->config->setRoutes($routes);
    }

    protected function mergeParameters(ParsedConfigDTO $config): void {
        $parameters = $this->config->getParameters();

        foreach ($config->getParameters() as $parameter) {
            $parameters[$parameter->name] = $parameter;
        }

        $this->config->setParameters($parameters);
    }

    /**
     * @throws ServiceException
     */
    public function mergeParsedConfigs(...$configs): ParsedConfigDTO {
        $this->resetParsedConfig();

        foreach ($configs as $config) {
            $this->mergeParsedConfig($config);
        }

        return $this->config;
    }

    protected function resetParsedConfig(): void {
        unset($this->config);

        $this->config = new ParsedConfigDTO();
    }
}
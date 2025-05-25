<?php

declare(strict_types=1);

namespace Runway\Service\Converter;

use Runway\Service\DTO\ConfigDTO;
use Runway\Service\DTO\ParsedConfigDTO;
use Runway\Service\DTO\ParsedServiceDTO;
use Runway\Service\DTO\ServiceDTO;
use Runway\Service\Exception\ServiceDeclaredMoreThanOnceException;

class ConfigConverter implements IConfigConverter {
    /**
     * @var ServiceDTO[]
     */
    protected array $services = [];

    public function convertFromParsedService(ParsedServiceDTO $parsedServiceDTO): ServiceDTO {
        return new ServiceDTO(
            name: $parsedServiceDTO->getName(),
            class: $parsedServiceDTO->getClass(),
            decoratee: $parsedServiceDTO->getDecorates(),
            ancestorService: '',
            decorators: [],
            arguments: $parsedServiceDTO->getArguments(),
            events: $parsedServiceDTO->getEvents(),
            tags: $parsedServiceDTO->getTags(),
            filePath: $parsedServiceDTO->getFilePath()
        );
    }

    public function convertFromParsedConfig(ParsedConfigDTO $parsedConfig): ConfigDTO {
        $this->services = [];
        $result = new ConfigDTO(
            [],
            $parsedConfig->getRoutes(),
            $parsedConfig->getParameters()
        );

        foreach ($parsedConfig->getParsedServiceConfig() as $service) {
            $serviceName = $service->getName();

            $newService = $this->convertFromParsedService($service);

            if (array_key_exists($serviceName, $this->services)) {
                throw new ServiceDeclaredMoreThanOnceException($newService, $this->services[$serviceName]);
            }

            $this->services[$serviceName] = $newService;
        }

        foreach ($this->services as $serviceName => $serviceDTO) {
            if (
                ($decorates = $serviceDTO->getDecoratee())
                && array_key_exists($decorates, $this->services)
            ) {
                $this->services[$decorates]->addDecorator($serviceName);
            }

            $this->services[$serviceName]->setAncestorService(
                $this->getAncestorService($serviceName)
            );
        }

        return $result->setServiceConfig($this->services);
    }

    protected function getAncestorService($serviceName): string {
        while (
            array_key_exists($serviceName, $this->services)
            && ($parent = $this->services[$serviceName]->getDecoratee())
        ) {
            $serviceName = $parent;
        }

        return array_key_exists($serviceName, $this->services)
            ? $serviceName
            : '';
    }
}
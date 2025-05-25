<?php

declare(strict_types=1);

namespace Runway\Service\DataBuilder;

use Runway\Event\DTO\EventDTO;
use Runway\Event\Exception\EmptyEventMethod;
use Runway\Event\Exception\EmptyEventName;
use Runway\Event\Exception\EventException;
use Runway\Event\Exception\EventMethodDoesNotExist;
use Runway\Service\DTO\ParameterDTO;
use Runway\Service\DTO\ParsedServiceDTO;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\DTO\ServiceArgumentDTO;
use Runway\Service\DTO\ServiceDTO;
use Runway\Service\DTO\TagDTO;
use Runway\Service\Exception\Route\RouteException;

class ParsedConfigBuilder implements IParsedConfigBuilder {
    protected array $rawData = [];

    protected string $filePath = '';

    /**
     * @throws EventException
     */
    public function buildParsedService(string $serviceName, ?array $rawData): ParsedServiceDTO {
        $serviceFQN = ($rawData['class'] ?? null)
            ? (string)$rawData['class']
            : $serviceName;

        return new ParsedServiceDTO(
            name: $serviceName,
            class: $serviceFQN,
            decorates: (string)($rawData['decorates'] ?? null),
            arguments: $this->buildServiceArguments(
                (array)($rawData['arguments'] ?? [])
            ),
            events: $this->buildServiceEvents(
                (array)($rawData['events'] ?? []),
                $serviceName,
                $serviceFQN
            ),
            tags: $this->buildServiceTags(
                (array)($rawData['tags'] ?? [])
            ),
            filePath: $this->filePath
        );
    }

    public function setRawData(array $rawData): static {
        $this->rawData = $rawData;

        return $this;
    }

    public function setFilePath(string $filepath): static {
        $this->filePath = $filepath;

        return $this;
    }

    /**
     * @return RouteDTO[]
     *
     * @throws RouteException
     */
    public function buildRoutes(): array {
        $routes = [];

        foreach ((array)($this->rawData['routes'] ?? []) as $routeName => $rawData) {
            $routes[] = $this->buildRoute($routeName, $rawData);
        }

        return $routes;
    }

    /**
     * @throws RouteException
     */
    protected function buildRoute(string $routeName, array $rawData): RouteDTO {
        if (empty($rawData['path'])) {
            $this->throwRouteException("Empty route path");
        }

        [$controller, $method] = $this->getRouteControllerAndMethod($rawData);

        return new RouteDTO(
            name: $routeName,
            path: (string)$rawData['path'],
            controller: $controller,
            method: $method,
            priority: (int)($rawData['priority'] ?? 0),
            description: (string)($rawData['description'] ?? ''),
            filePath: $this->filePath
        );
    }

    /**
     * @return array{0: string, 1: string}
     *
     * @throws RouteException
     */
    protected function getRouteControllerAndMethod(array $rawData): array {
        $controller = (string)($rawData['controller'] ?? '');

        if (empty($controller)) {
            $this->throwRouteException("Empty route controller");
        }

        [$controller, $method] = explode('::', $controller, 2);

        if (empty($controller)) {
            $this->throwRouteException("Empty route controller");
        }

        if (empty($method)) {
            $this->throwRouteException("Empty route method");
        }

        return [$controller, $method];
    }

    /**
     * @throws RouteException
     */
    protected function throwRouteException(string $msgText): never {
        throw new RouteException(
            $this->getStubRouteDTO(),
            $msgText,
        );
    }

    protected function getStubRouteDTO(): RouteDTO {
        return new RouteDTO(
            name: "",
            path: "",
            controller: "",
            method: "",
            priority: 0,
            description: "",
            filePath: $this->filePath
        );
    }

    /**
     * @return ParameterDTO[]
     */
    public function buildParameters(): array {
        $result = [];

        foreach ((array)($this->rawData['parameters'] ?? []) as $name => $value) {
            $result[] = $this->buildParameter($name, $value);
        }

        return $result;
    }

    protected function buildParameter(string $name, mixed $value): ParameterDTO {
        return new ParameterDTO(
            name: $name,
            value: $value
        );
    }

    /**
     * @return ParsedServiceDTO[]
     *
     * @throws EventException
     */
    public function buildParsedServices(): array {
        $result = [];

        foreach ((array)($this->rawData['services'] ?? []) as $serviceName => $serviceRawData) {
            $result[] = $this->buildParsedService($serviceName, $serviceRawData);
        }

        return $result;
    }

    /**
     * @param array $argumentsRawData
     *
     * @return ServiceArgumentDTO[]
     */
    protected function buildServiceArguments(array $argumentsRawData): array {
        $result = [];

        foreach ($argumentsRawData as $name => $value) {
            $result[] = new ServiceArgumentDTO($name, $value);
        }

        return $result;
    }

    /**
     * @throws EventException
     */
    protected function buildServiceEvents(array $eventsRawData, string $serviceName, string $serviceFQN): array {
        $result = [];

        foreach ($eventsRawData as $eventData) {
            $event = new EventDTO(
                (string)($eventData['event'] ?? ''),
                $serviceName,
                (string)($eventData['method'] ?? '')
            );

            $this->validateEvent($event, $serviceName, $serviceFQN);

            $result[] = $event;
        }

        return $result;
    }

    /**
     * @throws EventException
     */
    protected function validateEvent(EventDTO $eventDTO, string $serviceName, string $serviceFQN): void {
        $eventName = $eventDTO->getName();
        $eventMethod = $eventDTO->getMethod();
        $serviceDTOForExceptions = new ServiceDTO(
            name: $serviceName,
            class: $serviceFQN,
            filePath: $this->filePath
        );

        if (!$eventName) {
            throw new EmptyEventName($serviceDTOForExceptions, $eventDTO);
        }

        if (!$eventMethod) {
            throw new EmptyEventMethod($serviceDTOForExceptions, $eventDTO);
        }

        if (!method_exists($serviceFQN, $eventMethod)) {
            throw new EventMethodDoesNotExist($serviceDTOForExceptions, $eventDTO);
        }
    }

    protected function buildServiceTags(array $tagsRawData): array {
        return array_map(
            static function (array $tagRawData): TagDTO {
                $extra = $tagRawData;
                unset($extra['name']);

                return new TagDTO(
                    name: $tagRawData['name'],
                    extra: $extra
                );
            },
            $tagsRawData
        );
    }
}
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
use Runway\Service\Exception\ConfigurationException;
use Runway\Service\Exception\Route\RouteException;

class ParsedConfigBuilder implements IParsedConfigBuilder {
    /**
     * @var array<mixed>
     */
    protected array $rawData = [];

    protected string $filePath = '';

    /**
     * @param array<mixed>|null $rawData
     *
     * @throws EventException
     */
    public function buildParsedService(string $serviceName, ?array $rawData): ParsedServiceDTO {
        $rawData ??= [];
        $serviceFQN = $this->getString($rawData, 'class') ?: $serviceName;

        return new ParsedServiceDTO(
            name: $serviceName,
            class: $serviceFQN,
            decorates: $this->getString($rawData, 'decorates'),
            arguments: $this->buildServiceArguments(
                (array)($rawData['arguments'] ?? [])
            ),
            events: $this->buildServiceEvents(
                (array)($rawData['events'] ?? []),
                $serviceName,
                $serviceFQN
            ),
            tags: $this->buildServiceTags(
                (array)($rawData['tags'] ?? []),
                $serviceName
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
            if (!is_array($rawData)) {
                $this->throwRouteException("Route \"$routeName\" is not a map of its options");
            }

            $routes[] = $this->buildRoute((string)$routeName, $rawData);
        }

        return $routes;
    }

    /**
     * @param array<mixed> $rawData
     *
     * @throws RouteException
     */
    protected function buildRoute(string $routeName, array $rawData): RouteDTO {
        $path = $this->getString($rawData, 'path');

        if ($path === '') {
            $this->throwRouteException("Empty route path");
        }

        [$controller, $method] = $this->getRouteControllerAndMethod($rawData);
        $priority = $rawData['priority'] ?? 0;

        return new RouteDTO(
            name: $routeName,
            path: $path,
            controller: $controller,
            method: $method,
            priority: is_numeric($priority) ? (int)$priority : 0,
            description: $this->getString($rawData, 'description'),
            filePath: $this->filePath,
            methods: $this->getRouteMethods($rawData),
            requirements: $this->getRouteRequirements($rawData)
        );
    }

    /**
     * "methods" can be a list ([GET, POST]) or a single method (GET).
     *
     * @param array<mixed> $rawData
     *
     * @return string[]
     */
    protected function getRouteMethods(array $rawData): array {
        return array_values(array_unique(array_map(
            static fn(mixed $method): string => strtoupper(is_scalar($method) ? (string)$method : ''),
            (array)($rawData['methods'] ?? [])
        )));
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return array<string, string> Placeholder name => regexp.
     */
    protected function getRouteRequirements(array $rawData): array {
        $requirements = [];

        foreach ((array)($rawData['requirements'] ?? []) as $name => $requirement) {
            $requirements[(string)$name] = is_scalar($requirement) ? (string)$requirement : '';
        }

        return $requirements;
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return array{0: string, 1: string}
     *
     * @throws RouteException
     */
    protected function getRouteControllerAndMethod(array $rawData): array {
        $controller = $this->getString($rawData, 'controller');

        if ($controller === '') {
            $this->throwRouteException("Empty route controller");
        }

        // "Controller" without "::method" is an empty method.
        [$controller, $method] = explode("::", $controller, 2) + [1 => ""];

        if ($controller === '') {
            $this->throwRouteException("Empty route controller");
        }

        if ($method === '') {
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
            $result[] = $this->buildParameter((string)$name, $value);
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
            $result[] = $this->buildParsedService(
                (string)$serviceName,
                is_array($serviceRawData) ? $serviceRawData : null
            );
        }

        return $result;
    }

    /**
     * @param array<mixed> $argumentsRawData
     *
     * @return ServiceArgumentDTO[]
     */
    protected function buildServiceArguments(array $argumentsRawData): array {
        $result = [];

        foreach ($argumentsRawData as $name => $value) {
            $result[] = new ServiceArgumentDTO((string)$name, $value);
        }

        return $result;
    }

    /**
     * @param array<mixed> $eventsRawData
     *
     * @return EventDTO[]
     *
     * @throws EventException
     */
    protected function buildServiceEvents(array $eventsRawData, string $serviceName, string $serviceFQN): array {
        $result = [];

        foreach ($eventsRawData as $eventData) {
            $eventData = is_array($eventData) ? $eventData : [];

            $event = new EventDTO(
                $this->getString($eventData, 'event'),
                $serviceName,
                $this->getString($eventData, 'method')
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

    /**
     * A tag is either a map with the name and the extra options ("- name: router.route_provider") or just the name
     * ("- router.route_provider").
     *
     * @param array<mixed> $tagsRawData
     *
     * @return TagDTO[]
     */
    protected function buildServiceTags(array $tagsRawData, string $serviceName): array {
        return array_values(array_map(
            function (mixed $tagRawData) use ($serviceName): TagDTO {
                if (is_string($tagRawData)) {
                    $tagRawData = ['name' => $tagRawData];
                }

                $name = is_array($tagRawData) ? $this->getString($tagRawData, 'name') : '';

                if (!is_array($tagRawData) || $name === '') {
                    throw new ConfigurationException($this->filePath, "A tag of service $serviceName has no name.");
                }

                $extra = [];

                foreach ($tagRawData as $key => $value) {
                    if ($key !== 'name') {
                        $extra[(string)$key] = $value;
                    }
                }

                return new TagDTO(
                    name: $name,
                    extra: $extra
                );
            },
            $tagsRawData
        ));
    }

    /**
     * @param array<mixed> $rawData
     *
     * @return string The value of a scalar option, an empty string otherwise.
     */
    protected function getString(array $rawData, string $key): string {
        $value = $rawData[$key] ?? '';

        return is_scalar($value) ? (string)$value : '';
    }
}

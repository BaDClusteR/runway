<?php

declare(strict_types=1);

namespace Runway\Singleton;

use Runway\DataStorage\Exception\DBConnectionException;
use Runway\DataStorage\IDataStorageDriver;
use Runway\DataStorage\QueryBuilder\IQueryBuilder;
use Runway\Env\Provider\EnvVariablesProvider;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Event\Exception\EventException;
use Runway\Event\IEventDispatcher;
use Runway\Exception\CircularDependencyException;
use Runway\Service\DTO\ConfigDTO;
use Runway\Service\DTO\ServiceDTO;
use Runway\Service\DTO\TagDTO;
use Runway\Service\Exception\ClassNotFoundException;
use Runway\Service\Exception\ServiceDecoratorCallException;
use Runway\Service\Exception\ServiceException;
use Runway\Service\Exception\ServiceNotFoundException;
use Runway\Service\Exception\ServiceUntypedParameterInConstructor;
use Runway\Service\Provider\ConfigProvider;
use Runway\Service\Provider\IConfigProvider;
use Runway\Singleton;
use ReflectionClass;
use ReflectionNamedType;
use Runway\Singleton\DTO\DependencyChainLinkDTO;

class Container extends Singleton implements IContainer {
    protected ConfigDTO $config;

    /**
     * @var ServiceDTO[] $services
     */
    protected array $services = [];

    protected IConfigProvider $configProvider;

    protected ?IDataStorageDriver $dataStorageDriver = null;

    protected ?IQueryBuilder $queryBuilder = null;

    protected IEnvVariablesProvider $envVariablesProvider;

    /**
     * @var DependencyChainLinkDTO[]
     */
    protected static array $dependencyChain = [];

    /**
     * @throws EventException
     */
    public function __construct() {
        $this->configProvider = new ConfigProvider();
        $this->envVariablesProvider = new EnvVariablesProvider();
        $this->config = $this->configProvider->getConfig();

        foreach ($this->config->getServiceConfig() as $service) {
            $this->services[$service->getName()] = $service;
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $serviceName
     *
     * @return ($serviceName is class-string<T> ? T : mixed)
     */
    public function getService(string $serviceName, mixed $inner = null): mixed {
        return $this->doGetService($serviceName, $inner, true);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>|string $serviceName
     *
     * @return ($serviceName is class-string<T> ? T|null : mixed)
     */
    public function tryGetService(string $serviceName, mixed $inner = null): mixed {
        try {
            return $this->getService($serviceName, $inner);
        } catch (ServiceException) {
            return null;
        }
    }

    public function getEventDispatcher(): IEventDispatcher {
        return static::getInstance()->getService(IEventDispatcher::class);
    }

    /**
     * @throws DBConnectionException
     */
    public function getDataStorageDriver(): IDataStorageDriver {
        if (!$this->dataStorageDriver) {
            $dataStorageDriver = static::getInstance()->getService(IDataStorageDriver::class);

            // The driver reads the options via the env variables provider service, so its decorators are applied.
            $dataStorageDriver->connect(
                $dataStorageDriver->getConnectOptions()
            );

            $this->dataStorageDriver = $dataStorageDriver;
        }

        return $this->dataStorageDriver;
    }

    /**
     * @throws DBConnectionException
     */
    public function getQueryBuilder(): IQueryBuilder {
        if (!$this->queryBuilder) {
            $this->queryBuilder = $this->getService(IQueryBuilder::class)->setDataStorageDriver(
                $this->getDataStorageDriver()
            );
        }

        return $this->queryBuilder;
    }

    public function getServicesByTag(string $tag, array $extraFilters = []): array {
        /** @var array<string, array{service: mixed, priority: int}> $services */
        $services = [];

        foreach ($this->services as $serviceDTO) {
            $serviceName = $serviceDTO->getName();

            foreach ($serviceDTO->getTags() as $tagDTO) {
                if (
                    !isset($services[$serviceName])
                    && $this->isTagSuitable($tagDTO, $tag, $extraFilters)
                ) {
                    $priority = $tagDTO->getExtra()['priority'] ?? 0;

                    $services[$serviceName] = [
                        'service'  => $this->getService($serviceName),
                        'priority' => is_numeric($priority) ? (int)$priority : 0,
                    ];
                }
            }
        }

        // The sorting is stable: the services with the same priority keep the declaration order.
        usort(
            $services,
            static fn(array $a, array $b): int => $a['priority'] <=> $b['priority']
        );

        return array_map(
            static fn(array $service): mixed => $service['service'],
            $services
        );
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function isTagSuitable(TagDTO $tag, string $tagName, array $extra): bool {
        if ($tag->getName() !== $tagName) {
            return false;
        }

        $tagExtra = $tag->getExtra();

        return !array_any(
            $extra,
            static fn(mixed $value, string $key): bool => !array_key_exists($key, $tagExtra)
                                                          || $tagExtra[$key] !== $value
        );
    }

    protected function doGetService(
        string $serviceName,
        mixed  $inner,
        bool   $isPublic
    ): mixed {
        $this->checkForCircularDependency($serviceName);

        $this->addDependencyChainLink($serviceName);

        // The link is removed even if the service cannot be created: otherwise the next request of the service (e.g.
        // after tryGetService()) would be taken for a circular dependency.
        try {
            $serviceDTO = $this->getServiceDTOByServiceName($serviceName);

            $this->validateServiceCall($serviceDTO, $isPublic);

            $serviceFQN = $serviceDTO->getClass();

            $service = is_subclass_of($serviceFQN, Singleton::class)
                ? $serviceFQN::getInstance()
                : $this->createServiceInstance($serviceDTO, $inner);

            foreach ($serviceDTO->getDecorators() as $decorator) {
                $service = $this->doGetService($decorator, $service, false);
            }
        } finally {
            $this->removeDependencyChainLink();
        }

        return $service;
    }

    protected function addDependencyChainLink(string $serviceName): void {
        $filesToSkip = $this->getFilePathsToSkipInDependencyChain();

        $firstOuterTrace = array_find(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            static fn(array $trace): bool => !in_array($trace['file'] ?? '', $filesToSkip, true)
        ) ?? [];

        $class = $firstOuterTrace['class'] ?? '';
        $function = $firstOuterTrace['function'] ?? '';

        // Every service gets a link (even without an outer call), so removeDependencyChainLink() removes its own one.
        static::$dependencyChain[] = new DependencyChainLinkDTO(
            serviceName: $serviceName,
            fileName: $firstOuterTrace['file'] ?? '',
            line: $firstOuterTrace['line'] ?? 0,
            method: $class . ($class ? "::" : "") . $function,
        );
    }

    /**
     * @return string[]
     */
    protected function getFilePathsToSkipInDependencyChain(): array {
        return [
            __FILE__
        ];
    }

    protected function removeDependencyChainLink(): void {
        array_pop(static::$dependencyChain);
    }

    protected function checkForCircularDependency(string $serviceName): void {
        foreach (static::$dependencyChain as $i => $dependencyChain) {
            if ($dependencyChain->serviceName === $serviceName) {
                throw new CircularDependencyException(
                    array_slice(static::$dependencyChain, $i)
                );
            }
        }
    }

    protected function validateServiceCall(ServiceDTO $serviceDTO, bool $isPublic): void {
        if ($isPublic && $serviceDTO->getDecoratee()) {
            throw new ServiceDecoratorCallException($serviceDTO);
        }
    }

    protected function createServiceInstance(ServiceDTO $serviceDTO, mixed $inner): mixed {
        $serviceFQN = $serviceDTO->getClass();

        if (!class_exists($serviceFQN)) {
            throw new ClassNotFoundException($serviceFQN, $serviceDTO);
        }

        $classReflection = new ReflectionClass($serviceFQN);

        $parameters = [];

        foreach ($classReflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();
            $paramName = $parameter->getName();

            if ($type === null) {
                throw new ServiceUntypedParameterInConstructor($serviceDTO, $paramName);
            }

            // A union type ("A|B") cannot be a service.
            $paramType = ($type instanceof ReflectionNamedType) ? $type->getName() : '';

            if ($paramType !== '' && $paramType === $serviceDTO->getAncestorService()) {
                $parameters[$paramName] = $inner;
            } elseif ($serviceDTO->isArgumentDefined($paramName)) {
                $parameters[$paramName] = $this->getArgumentValue(
                    $serviceDTO->getArgumentValue($paramName),
                    $inner
                );
            } elseif ($parameter->isDefaultValueAvailable()) {
                $parameters[$paramName] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $parameters[$paramName] = null;
            } else {
                $parameters[$paramName] = $this->getService($paramType);
            }
        }

        return $classReflection->newInstanceArgs($parameters);
    }

    protected function getArgumentValue(mixed $rawArgumentValue, mixed $inner): mixed {
        if (is_string($rawArgumentValue)) {
            if (str_starts_with($rawArgumentValue, '@')) {
                $serviceName = substr($rawArgumentValue, 1);

                return $serviceName === 'inner'
                    ? $inner
                    : $this->getService($serviceName);
            }

            if (str_starts_with($rawArgumentValue, '%')) {
                $parameterName = substr($rawArgumentValue, 1);

                return $this->configProvider->getConfig()->getParameter($parameterName);
            }

            if (str_starts_with($rawArgumentValue, '#')) {
                $envVariableName = substr($rawArgumentValue, 1);

                return $this->envVariablesProvider->getEnvVariable($envVariableName);
            }
        }

        return $rawArgumentValue;
    }

    /**
     * @throws ServiceNotFoundException
     */
    protected function getServiceDTOByServiceName(string $serviceName): ServiceDTO {
        if ($this->hasService($serviceName)) {
            return $this->services[$serviceName];
        }

        throw new ServiceNotFoundException(
            new ServiceDTO($serviceName)
        );
    }

    public function getServiceTagsByName(string $tagName): array {
        $result = [];

        foreach ($this->services as $serviceDTO) {
            foreach ($serviceDTO->getTags() as $tagDTO) {
                if ($tagDTO->getName() === $tagName) {
                    $result[] = [
                        'serviceName' => $serviceDTO->getName(),
                        'extra' => $tagDTO->getExtra(),
                    ];
                }
            }
        }

        return $result;
    }

    public function hasService(string $serviceName): bool {
        return array_key_exists($serviceName, $this->services);
    }
}

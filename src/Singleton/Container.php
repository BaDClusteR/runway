<?php

declare(strict_types=1);

namespace Runway\Singleton;

use Runway\DataStorage\DTO\DBConnectOptionsDTO;
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
use ReflectionException;
use ReflectionParameter;

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
     * @var \Runway\Singleton\DTO\DependencyChainLinkDTO[]
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

    public function getService(string $serviceName, mixed $inner = null): mixed {
        return $this->doGetService($serviceName, $inner, true);
    }

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
            $this->dataStorageDriver = static::getInstance()->getService(IDataStorageDriver::class);

            $this->dataStorageDriver->connect(
                $this->getDataStorageDriverConnectOptions()
            );
        }

        return $this->dataStorageDriver;
    }

    protected function getDataStorageDriverConnectOptions(): DBConnectOptionsDTO {
        return new DBConnectOptionsDTO(
            user: $this->envVariablesProvider->getEnvVariable("DB_USER"),
            password: $this->envVariablesProvider->getEnvVariable("DB_PASSWORD"),
            dbName: $this->envVariablesProvider->getEnvVariable("DB_NAME"),
            host: $this->envVariablesProvider->getEnvVariable("DB_HOST"),
            port: $this->envVariablesProvider->getEnvVariable("DB_PORT"),
            tableNamePrefix: $this->envVariablesProvider->getEnvVariable("DB_PREFIX"),
            encoding: $this->envVariablesProvider->getEnvVariable("DB_ENCODING")
        );
    }

    /**
     * @throws DBConnectionException
     */
    public function getQueryBuilder(): IQueryBuilder {
        if (!$this->queryBuilder) {
            $this->queryBuilder = $this->getService(IQueryBuilder::class);

            $this->queryBuilder->setDataStorageDriver(
                $this->getDataStorageDriver()
            );
        }

        return $this->queryBuilder;
    }

    public function getServicesByTag(string $tag, array $extraFilters = []): array {
        $services = [];

        foreach ($this->services as $serviceDTO) {
            $serviceName = $serviceDTO->getName();

            foreach ($serviceDTO->getTags() as $tagDTO) {
                if (
                    empty($services[$serviceName])
                    && $this->isTagSuitable($tagDTO, $tag, $extraFilters)
                ) {
                    $services[$serviceName] = $this->getService($serviceName);
                }
            }
        }

        return $services;
    }

    protected function isTagSuitable(TagDTO $tag, string $tagName, array $extra): bool {
        if ($tag->getName() !== $tagName) {
            return false;
        }

        if ($extra) {
            $tagExtra = $tag->getExtra();

            if (
                array_any(
                    $extra,
                    static fn($value, $key) => !array_key_exists($key, $tagExtra)
                        || $tagExtra[$key] !== $value
                )
            ) {
                return false;
            }
        }

        return true;
    }

    protected function doGetService(
        string $serviceName,
        mixed  $inner,
        bool   $isPublic
    ): mixed {
        $this->checkForCircularDependency($serviceName);

        $this->addDependencyChainLink($serviceName);
        $serviceDTO = $this->getServiceDTOByServiceName($serviceName);

        $this->validateServiceCall($serviceDTO, $isPublic);

        $serviceFQN = $serviceDTO->getClass();

        $service = is_subclass_of($serviceFQN, Singleton::class)
            ? $serviceFQN::getInstance()
            : $this->createServiceInstance($serviceDTO, $inner);

        if ($decorators = $serviceDTO->getDecorators()) {
            foreach ($decorators as $decorator) {
                $inner = $service;
                $service = $this->doGetService($decorator, $inner, false);
            }
        }

        $this->removeDependencyChainLink();

        return $service;
    }

    protected function addDependencyChainLink(string $serviceName): void {
        $filesToSkip = $this->getFilePathsToSkipInDependencyChain();

        $firstOuterTrace = array_find(
            debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            static fn(array $trace): bool => !in_array((string)($trace['file'] ?? ''), $filesToSkip, true)
        );

        if ($firstOuterTrace) {
            $class = (string)($firstOuterTrace['class'] ?? '');
            $function = (string)($firstOuterTrace['function'] ?? '');

            static::$dependencyChain[] = new \Runway\Singleton\DTO\DependencyChainLinkDTO(
                serviceName: $serviceName,
                fileName: (string)($firstOuterTrace['file'] ?? ''),
                line: (int)($firstOuterTrace['line'] ?? 0),
                method: $class . ($class ? "::" : "") . $function,
            );
        }
    }

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

        try {
            $classReflection = new ReflectionClass($serviceFQN);
        } catch (ReflectionException) {
            throw new ClassNotFoundException($serviceFQN, $serviceDTO);
        }

        $parameters = [];

        /** @var ReflectionParameter $parameter */
        foreach ((array)$classReflection->getConstructor()?->getParameters() as $parameter) {
            $paramType = $parameter->getType()?->getName();
            $paramName = $parameter->getName();

            if (!$paramType) {
                throw new ServiceUntypedParameterInConstructor($serviceDTO, $parameter->getName());
            }

            if ($paramType === $serviceDTO->getAncestorService()) {
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
                try {
                    $parameters[$paramName] = $this->getService(
                        $paramType
                    );
                } catch (ServiceNotFoundException $e) {
                    try {
                        if ($defaultValue = $parameter->getDefaultValue()) {
                            $parameters[$paramName] = $defaultValue;
                        }
                    } catch (ReflectionException) {
                    }

                    if (
                        !array_key_exists($paramName, $parameters)
                        && $parameter->allowsNull()
                    ) {
                        $parameters[$paramName] = null;
                    }

                    if (!array_key_exists($paramName, $parameters)) {
                        throw $e;
                    }
                }
            }
        }

        return new $serviceFQN(...$parameters);
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

    public function hasService(string $serviceName): bool {
        return array_key_exists($serviceName, $this->services);
    }
}

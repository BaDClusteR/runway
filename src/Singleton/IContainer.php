<?php

namespace Runway\Singleton;

use Runway\DataStorage\Exception\DBConnectionException;
use Runway\DataStorage\IDataStorageDriver;
use Runway\DataStorage\QueryBuilder\IQueryBuilder;
use Runway\Event\IEventDispatcher;
use Runway\ISingleton;
use Runway\Service\Exception\ServiceException;

interface IContainer extends ISingleton {
    /**
     * A service named by an interface or a class is an instance of it.
     *
     * @template T of object
     *
     * @param class-string<T>|string $serviceName
     *
     * @return ($serviceName is class-string<T> ? T : mixed)
     *
     * @throws ServiceException
     */
    public function getService(string $serviceName, mixed $inner = null): mixed;

    /**
     * @template T of object
     *
     * @param class-string<T>|string $serviceName
     *
     * @return ($serviceName is class-string<T> ? T|null : mixed) Null if the service cannot be created.
     */
    public function tryGetService(string $serviceName, mixed $inner = null): mixed;

    public function getEventDispatcher(): IEventDispatcher;

    /**
     * @throws DBConnectionException
     */
    public function getDataStorageDriver(): IDataStorageDriver;

    /**
     * @throws DBConnectionException
     */
    public function getQueryBuilder(): IQueryBuilder;

    /**
     * The services with the tag, by the "priority" of the tag (the lower first).
     *
     * @param array<string, mixed> $extraFilters The other options the tag must have, e.g. ['type' => 'json'].
     *
     * @return list<mixed>
     */
    public function getServicesByTag(string $tag, array $extraFilters = []): array;

    /**
     * Returns tag metadata for all services matching a given tag name.
     *
     * @return list<array{serviceName: string, extra: array<string, mixed>}>
     */
    public function getServiceTagsByName(string $tagName): array;

    public function hasService(string $serviceName): bool;
}

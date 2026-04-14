<?php

namespace Runway\Singleton;

use Runway\DataStorage\Exception\DBConnectionException;
use Runway\DataStorage\IDataStorageDriver;
use Runway\DataStorage\QueryBuilder\IQueryBuilder;
use Runway\Event\IEventDispatcher;
use Runway\ISingleton;

interface IContainer extends ISingleton {
    public function getService(string $serviceName, mixed $inner = null): mixed;

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

    public function getServicesByTag(string $tag, array $extraFilters = []): array;

    /**
     * Returns tag metadata for all services matching a given tag name.
     *
     * @return array<array{serviceName: string, extra: array<string, mixed>}>
     */
    public function getServiceTagsByName(string $tagName): array;

    public function hasService(string $serviceName): bool;
}
<?php

namespace Runway\DataStorage;

use Runway\DataStorage\DTO\DataStorageConnectionOptionsDTO;
use Runway\DataStorage\Exception\DBConnectionException;
use Runway\DataStorage\Exception\DBException;

/**
 * The query variables ($vars) are an array (":name" => value or a list for "?"), a single value or null (none).
 */
interface IDataStorageDriver {
    /**
     * Called by the container once, before the first query (see Container::getDataStorageDriver()).
     *
     * @return mixed The connection (e.g. \PDO).
     *
     * @throws DBConnectionException
     */
    public function connect(DataStorageConnectionOptionsDTO $options): mixed;

    /**
     * @return array<array<string, mixed>> The rows.
     *
     * @throws DBException
     */
    public function getResult(string $query, mixed $vars = []): array;

    /**
     * @return iterable<array<string, mixed>> The rows one by one.
     *
     * @throws DBException
     */
    public function getResultsIterator(string $query, mixed $vars = []): iterable;

    /**
     * @throws DBException
     */
    public function execute(string $query, mixed $vars = []): void;

    /**
     * @return array<string, mixed> The first row, an empty array if there are no rows.
     *
     * @throws DBException
     */
    public function getFirstResult(string $query, mixed $vars = []): array;

    /**
     * @param int|string $columnName The name of the column or its index.
     *
     * @return list<mixed> The values of the column.
     *
     * @throws DBException
     */
    public function getColumn(string $query, mixed $vars = [], int|string $columnName = ""): array;

    /**
     * @return mixed The first column of the first row, null if there are no rows.
     *
     * @throws DBException
     */
    public function getFirstScalarResult(string $query, mixed $vars = []): mixed;

    public function getLastInsertId(): string;

    public function getConnectOptions(): DataStorageConnectionOptionsDTO;
}

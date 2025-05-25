<?php

namespace Runway\DataStorage;

use Runway\DataStorage\Exception\DBException;

interface IDataStorageDriver {
    /**
     * @throws DBException
     */
    public function getResult(string $query, mixed $vars = []): array;

    /**
     * @throws DBException
     */
    public function getResultsIterator(string $query, mixed $vars = []): iterable;

    /**
     * @throws DBException
     */
    public function execute(string $query, mixed $vars = []): void;

    /**
     * @throws DBException
     */
    public function getFirstResult($query, mixed $vars = []): array;

    /**
     * @throws DBException
     */
    public function getColumn(string $query, mixed $vars = [], int|string $columnName = ""): array;

    /**
     * @throws DBException
     */
    public function getFirstScalarResult(string $query, mixed $vars = []): mixed;

    public function getLastInsertId(): string;
}
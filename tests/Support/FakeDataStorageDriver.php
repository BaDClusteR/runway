<?php

declare(strict_types=1);

namespace Runway\Tests\Support;

use Runway\DataStorage\DTO\DataStorageConnectionOptionsDTO;
use Runway\DataStorage\IDataStorageDriver;

/**
 * A data storage that records the queries and gives the prepared results: the SQL of the query builder and the models
 * without a database.
 */
final class FakeDataStorageDriver implements IDataStorageDriver {
    /**
     * @var list<array{method: string, query: string, vars: mixed}>
     */
    public array $queries = [];

    /**
     * @var list<array<array<string, mixed>>> The results of the next queries (rows), in the order of the queries.
     */
    public array $results = [];

    public string $lastInsertId = '';

    /**
     * @var \Throwable|null Thrown by the next query.
     */
    public ?\Throwable $exception = null;

    public ?DataStorageConnectionOptionsDTO $connectedWith = null;

    /**
     * @param array<array<string, mixed>> ...$results
     */
    public function willReturn(array ...$results): self {
        $this->results = [...$this->results, ...array_values($results)];

        return $this;
    }

    public function connect(DataStorageConnectionOptionsDTO $options): mixed {
        $this->connectedWith = $options;

        return null;
    }

    public function getResult(string $query, mixed $vars = []): array {
        return $this->record(__FUNCTION__, $query, $vars);
    }

    public function getResultsIterator(string $query, mixed $vars = []): iterable {
        yield from $this->record(__FUNCTION__, $query, $vars);
    }

    public function execute(string $query, mixed $vars = []): void {
        $this->record(__FUNCTION__, $query, $vars);
    }

    public function getFirstResult(string $query, mixed $vars = []): array {
        return $this->record(__FUNCTION__, $query, $vars)[0] ?? [];
    }

    public function getColumn(string $query, mixed $vars = [], int|string $columnName = ""): array {
        return array_values(array_map(
            static fn(array $row): mixed => $row[$columnName] ?? null,
            $this->record(__FUNCTION__, $query, $vars)
        ));
    }

    public function getFirstScalarResult(string $query, mixed $vars = []): mixed {
        $row = $this->record(__FUNCTION__, $query, $vars)[0] ?? [];

        return $row ? reset($row) : null;
    }

    public function getLastInsertId(): string {
        return $this->lastInsertId;
    }

    public function getConnectOptions(): DataStorageConnectionOptionsDTO {
        return new DataStorageConnectionOptionsDTO('fake:');
    }

    /**
     * @return string[] The SQL of the recorded queries.
     */
    public function getQueries(): array {
        return array_map(static fn(array $query): string => $query['query'], $this->queries);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function record(string $method, string $query, mixed $vars): array {
        $this->queries[] = ['method' => $method, 'query' => $query, 'vars' => $vars];

        if ($this->exception !== null) {
            $exception = $this->exception;
            $this->exception = null;

            throw $exception;
        }

        return array_shift($this->results) ?? [];
    }
}

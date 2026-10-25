<?php

declare(strict_types=1);

namespace Runway\DataStorage;

use Exception;
use PDOException;
use PDOStatement;
use Runway\DataStorage\DTO\DataStorageConnectionOptionsDTO;
use Runway\DataStorage\Event\PDO\PDOConnectionErrorEnvelope;
use Runway\DataStorage\Event\PDO\PDOErrorEnvelope;
use Runway\DataStorage\Event\PDO\PDONotConnectedEnvelope;
use Runway\DataStorage\Event\PDO\PDOStatementErrorEnvelope;
use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\Exception\DBNotConnectedException;
use Runway\DataStorage\Exception\PDO\PDOConnectionException;
use Runway\DataStorage\Exception\PDO\PDOStatementException;
use Runway\DataStorage\Exception\PDO\PDOStatementPreparationException;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Event\IEventDispatcher;

class PDO implements IDataStorageDriver {
    public function __construct(
        protected IEnvVariablesProvider $envVariablesProvider,
        protected IEventDispatcher $eventDispatcher,
    ) {}

    private ?\PDO $connection = null;

    private string $tableNamePrefix = "";

    /**
     * @throws PDOConnectionException
     */
    public function connect(DataStorageConnectionOptionsDTO $options): ?\PDO {
        try {
            $this->connection = new \PDO(
                $options->dsn,
                $options->user,
                $options->password
            );

            $this->tableNamePrefix = $options->tableNamePrefix;
        } catch (Exception $e) {
            $this->handleConnectionException($e, $options->dsn);
        }

        return $this->connection;
    }

    protected function isConnected(): bool {
        return $this->connection !== null;
    }

    /**
     * @throws DBException
     */
    public function getResult(string $query, mixed $vars = []): array {
        /** @var array<array<string, mixed>> FETCH_ASSOC gives the rows as column => value. */
        return $this->prepareAndExecute($query, $vars)
                    ->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * @throws DBException
     */
    public function getResultsIterator(string $query, mixed $vars = []): iterable {
        $statement = $this->prepareAndExecute($query, $vars);

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * @throws DBException
     */
    public function execute(string $query, mixed $vars = []): void {
        $this->prepareAndExecute($query, $vars);
    }

    /**
     * @throws DBException
     */
    public function getFirstResult(string $query, mixed $vars = []): array {
        $result = $this->prepareAndExecute($query, $vars)->fetch(\PDO::FETCH_ASSOC);

        return is_array($result)
            ? $result
            : [];
    }

    /**
     * @throws DBException
     */
    public function getColumn(string $query, mixed $vars = [], int|string $columnName = ""): array {
        $data = $this->prepareAndExecute($query, $vars)
                     ->fetchAll(
                         is_numeric($columnName)
                             ? \PDO::FETCH_NUM
                             : \PDO::FETCH_ASSOC
                     );

        return array_values(array_map(
            static fn(mixed $row): mixed => is_array($row) ? ($row[$columnName] ?? null) : null,
            $data
        ));
    }

    /**
     * @throws DBException
     */
    public function getFirstScalarResult(string $query, mixed $vars = []): mixed {
        $line = $this->prepareAndExecute($query, $vars)->fetch(\PDO::FETCH_NUM);

        return $line[0] ?? null;
    }

    /**
     * @throws DBNotConnectedException
     * @throws PDOStatementPreparationException
     * @throws PDOStatementException
     */
    protected function prepareAndExecute(string $query, mixed $vars = []): PDOStatement {
        $vars = is_array($vars) ? $vars : (($vars === null) ? [] : [$vars]);

        if (!$this->isConnected()) {
            $this->handleDBNotConnectedError($query, $vars);
        }

        $statement = $this->prepareStatement($query);

        try {
            $result = $statement->execute($vars);

            if (!$result) {
                $this->handleStatementError($statement, $query);
            }
        } catch (PDOException $e) {
            $this->handleStatementError($statement, $query, $e);
        }

        return $statement;
    }

    private function addPrefixToTableNames(string $query): string {
        return (string)preg_replace("/`(.*?){(.*?)}(.*?)`/", "`\$1{$this->tableNamePrefix}\$2\$3`", $query);
    }

    protected function prepareQuery(string $query): string {
        return $this->addPrefixToTableNames($query);
    }

    /**
     * @throws PDOStatementPreparationException
     */
    protected function prepareStatement(string $query): PDOStatement {
        $query = $this->prepareQuery($query);
        $connection = $this->connection ?? throw new DBNotConnectedException();

        try {
            $result = $connection->prepare(
                $query,
                [
                    \PDO::ATTR_CURSOR  => \PDO::CURSOR_FWDONLY,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
                ]
            );
        } catch (PDOException $e) {
            $this->handleStatementPreparationException($query, $e);
        }

        return $result;
    }

    public function getLastInsertId(): string {
        return ((string)$this->connection?->lastInsertId());
    }

    /**
     * @throws PDOConnectionException
     */
    public function handleConnectionException(Exception $e, string $dsn): never {
        $this->eventDispatcher->dispatch(
            'db.connection.error',
            new PDOConnectionErrorEnvelope(
                (int)$e->getCode(),
                $e->getMessage(),
                $dsn
            )
        );

        // PDOException code can be a string SQLSTATE code (e.g. "HY000").
        throw new PDOConnectionException(
            $e->getMessage(),
            (int)$e->getCode(),
            $dsn,
            $e
        );
    }

    /**
     * @throws PDOStatementPreparationException
     */
    public function handleStatementPreparationException(string $query, PDOException $e): never {
        $this->eventDispatcher->dispatch(
            'db.error.prepare',
            new PDOErrorEnvelope(
                (int)$e->getCode(),
                $e->getMessage(),
                $query,
                $this->connection
            )
        );

        throw new PDOStatementPreparationException(
            sqlStateCode: (string)$e->getCode(),
            query: $query,
            connection: $this->connection,
            message: $e->getMessage(),
            code: (int)$e->getCode(),
            previous: $e
        );
    }

    /**
     * @throws PDOStatementException
     */
    private function handleStatementError(
        PDOStatement  $statement,
        string        $query,
        ?PDOException $previous = null
    ): never {
        $errorInfo = $statement->errorInfo();

        $sqlStateCode = is_scalar($errorInfo[0] ?? null) ? (string)$errorInfo[0] : '';
        $errorCode = is_numeric($errorInfo[1] ?? null) ? (int)$errorInfo[1] : 0;
        $errorMessage = is_scalar($errorInfo[2] ?? null) ? (string)$errorInfo[2] : '';
        $connection = $this->connection ?? throw new DBNotConnectedException();

        $this->eventDispatcher->dispatch(
            'db.error',
            new PDOStatementErrorEnvelope(
                sqlStateErrorCode: $sqlStateCode,
                code: $errorCode,
                message: $errorMessage,
                query: $query,
                statement: $statement,
                connection: $connection
            )
        );

        throw new PDOStatementException(
            sqlStateCode: $sqlStateCode,
            query: $query,
            statement: $statement,
            connection: $connection,
            message: $errorMessage,
            code: $errorCode,
            previous: $previous
        );
    }

    /**
     * @param array<mixed> $vars
     *
     * @throws DBNotConnectedException
     */
    protected function handleDBNotConnectedError(string $query, array $vars): never {
        $this->eventDispatcher->dispatch(
            'db.error.not_connected',
            new PDONotConnectedEnvelope(
                $query,
                $vars
            )
        );

        throw new DBNotConnectedException();
    }

    /**
     * The .env values are typed: a numeric password (DB_PASSWORD=12345) is an int, a missing prefix is null.
     */
    public function getConnectOptions(): DataStorageConnectionOptionsDTO {
        return new DataStorageConnectionOptionsDTO(
            dsn: $this->getStringEnvVariable('DSN') ?? '',
            user: $this->getStringEnvVariable('DB_USER'),
            password: $this->getStringEnvVariable('DB_PASSWORD'),
            tableNamePrefix: $this->getStringEnvVariable('DB_PREFIX') ?? ''
        );
    }

    protected function getStringEnvVariable(string $name): ?string {
        $value = $this->envVariablesProvider->getEnvVariable($name);

        return is_scalar($value)
            ? (string)$value
            : null;
    }
}
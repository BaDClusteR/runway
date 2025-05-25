<?php

declare(strict_types=1);

namespace Runway\DataStorage;

use Runway\DataStorage\DTO\DBConnectOptionsDTO;
use Runway\DataStorage\Event\PDO\PDOConnectionErrorEnvelope;
use Runway\DataStorage\Event\PDO\PDOErrorEnvelope;
use Runway\DataStorage\Event\PDO\PDONotConnectedEnvelope;
use Runway\DataStorage\Event\PDO\PDOStatementErrorEnvelope;
use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\Exception\DBNotConnectedException;
use Runway\DataStorage\Exception\PDO\PDOConnectionException;
use Runway\DataStorage\Exception\PDO\PDOStatementException;
use Runway\DataStorage\Exception\PDO\PDOStatementPreparationException;
use Runway\Singleton\Container;
use Exception;
use PDOException;
use PDOStatement;

class PDO implements IDataStorageDriver {
    private ?\PDO $connection;

    private string $tableNamePrefix = "";

    /**
     * @throws PDOConnectionException
     */
    public function connect(DBConnectOptionsDTO $options): ?\PDO {
        $dsn = "mysql:host={$options->getHost()};dbname={$options->getDbName()}";

        if ($encoding = $options->getEncoding()) {
            $dsn .= ";charset={$encoding}";
        }

        try {
            $this->connection = new \PDO(
                $dsn,
                $options->getUser(),
                $options->getPassword()
            );

            $this->tableNamePrefix = $options->getTableNamePrefix();
        } catch (Exception $e) {
            $this->handleConnectionException($e, $dsn);
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
    public function getFirstResult($query, mixed $vars = []): array {
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

        return array_map(
            static fn(array $row): mixed => ($row[$columnName] ?? null),
            $data
        );
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
        if (!$this->isConnected()) {
            $this->handleDBNotConnectedError($query, $vars);
        }

        if (is_scalar($vars)) {
            $vars = [$vars];
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
        return preg_replace("/`(.*?){(.*?)}(.*?)`/", "`\$1{$this->tableNamePrefix}\$2\$3`", $query);
    }

    protected function prepareQuery(string $query): string {
        return $this->addPrefixToTableNames($query);
    }

    /**
     * @throws PDOStatementPreparationException
     */
    protected function prepareStatement(string $query): PDOStatement {
        $query = $this->prepareQuery($query);

        try {
            $result = $this->connection->prepare(
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
        Container::getInstance()->getEventDispatcher()->dispatch(
            'db.connection.error',
            new PDOConnectionErrorEnvelope(
                $e->getCode(),
                $e->getMessage(),
                $dsn
            )
        );

        throw new PDOConnectionException(
            $e->getMessage(),
            $e->getCode(),
            $dsn,
            $e
        );
    }

    /**
     * @throws PDOStatementPreparationException
     */
    public function handleStatementPreparationException(string $query, PDOException $e): never {
        Container::getInstance()->getEventDispatcher()->dispatch(
            'db.error.prepare',
            new PDOErrorEnvelope(
                (int)$e->getCode(),
                $e->getMessage(),
                $query,
                $this->connection
            )
        );

        throw new PDOStatementPreparationException(
            $query,
            $this->connection,
            $e->getMessage(),
            (int)$e->getCode(),
            $e
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

        $sqlStateCode = (string)($errorInfo[0] ?? '');
        $errorCode = (int)($errorInfo[1] ?? 0);
        $errorMessage = (string)($errorInfo[2] ?? '');

        Container::getInstance()->getEventDispatcher()?->dispatch(
            'db.error',
            new PDOStatementErrorEnvelope(
                sqlStateErrorCode: $sqlStateCode,
                code: $errorCode,
                message: $errorMessage,
                query: $query,
                statement: $statement,
                connection: $this->connection
            )
        );

        throw new PDOStatementException(
            sqlStateCode: $sqlStateCode,
            query: $query,
            statement: $statement,
            connection: $this->connection,
            message: $errorMessage,
            code: $errorCode,
            previous: $previous
        );
    }

    /**
     * @throws DBNotConnectedException
     */
    protected function handleDBNotConnectedError(string $query, array $vars): never {
        Container::getInstance()->getEventDispatcher()?->dispatch(
            'db.error.not_connected',
            new PDONotConnectedEnvelope(
                $query,
                $vars
            )
        );

        throw new DBNotConnectedException();
    }
}
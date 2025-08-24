<?php

declare(strict_types=1);

namespace Runway\DataStorage\Exception\PDO;

use PDO;
use PDOStatement;
use Throwable;

class PDOStatementException extends PDOException {
    public function __construct(
        private readonly string       $sqlStateCode,
        string                        $query,
        private readonly PDOStatement $statement,
        PDO                           $connection,
        string                        $message = "",
        int                           $code = 0,
        ?Throwable                    $previous = null
    ) {
        parent::__construct($this->sqlStateCode, $query, $connection, $message, $code, $previous);
    }

    public function getStatement(): PDOStatement {
        return $this->statement;
    }

    public function getSqlStateCode(): string {
        return $this->sqlStateCode;
    }
}
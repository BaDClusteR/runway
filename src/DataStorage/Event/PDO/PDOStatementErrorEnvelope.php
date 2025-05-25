<?php

declare(strict_types=1);

namespace Runway\DataStorage\Event\PDO;

use PDO;
use PDOStatement;

readonly class PDOStatementErrorEnvelope extends PDOErrorEnvelope {
    public function __construct(
        private string       $sqlStateErrorCode,
        int                  $code,
        string               $message,
        string               $query,
        private PDOStatement $statement,
        PDO                  $connection
    ) {
        parent::__construct($code, $message, $query, $connection, $this->sqlStateErrorCode);
    }

    public function getStatement(): PDOStatement {
        return $this->statement;
    }
}
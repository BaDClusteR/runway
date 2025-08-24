<?php

declare(strict_types=1);

namespace Runway\DataStorage\Exception\PDO;

use Runway\DataStorage\Exception\DBException;
use PDO;
use Throwable;

class PDOException extends DBException {
    public function __construct(
        private readonly string $sqlStateCode = "",
        private readonly string $query = "",
        private readonly ?PDO   $connection = null,
        string                  $message = "",
        int                     $code = 0,
        ?Throwable              $previous = null
    ) {
        $query = str_replace("\n", "\\n", $query);

        $fullMessage = $query
            ? "Error while executing '$query': $message [code $this->sqlStateCode]"
            : "Error while executing unknown SQL query: $message [code $this->sqlStateCode]";

        parent::__construct($fullMessage, $code, $previous);
    }

    public function getQuery(): string {
        return $this->query;
    }

    public function getConnection(): ?PDO {
        return $this->connection;
    }
}
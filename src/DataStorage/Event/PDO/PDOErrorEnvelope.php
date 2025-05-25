<?php

declare(strict_types=1);

namespace Runway\DataStorage\Event\PDO;

use Runway\DataStorage\Event\DBErrorEnvelope;
use PDO;

readonly class PDOErrorEnvelope extends DBErrorEnvelope {
    public function __construct(
        int          $code,
        string       $message,
        string       $query,
        private ?PDO $connection = null,
        string       $sqlStateErrorCode = ''
    ) {
        parent::__construct($code, "Error while executing '$query'. $message", $query, $sqlStateErrorCode);
    }

    public function getConnection(): PDO {
        return $this->connection;
    }
}
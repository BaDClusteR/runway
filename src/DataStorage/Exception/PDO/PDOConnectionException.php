<?php

declare(strict_types=1);

namespace Runway\DataStorage\Exception\PDO;

use Runway\DataStorage\Exception\DBConnectionException;
use Throwable;

class PDOConnectionException extends DBConnectionException {
    public function __construct(
        string                  $message = "",
        int                     $code = 0,
        private readonly string $dsn = "",
        ?Throwable              $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getDsn(): string {
        return $this->dsn;
    }
}
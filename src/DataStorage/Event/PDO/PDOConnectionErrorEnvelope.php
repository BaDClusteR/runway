<?php

declare(strict_types=1);

namespace Runway\DataStorage\Event\PDO;

use Runway\DataStorage\Event\DBConnectionErrorEnvelope;

readonly class PDOConnectionErrorEnvelope extends DBConnectionErrorEnvelope {
    public function __construct(
        int            $code,
        string         $message,
        private string $dsn
    ) {
        parent::__construct($code, $message);
    }

    public function getDsn(): string {
        return $this->dsn;
    }
}
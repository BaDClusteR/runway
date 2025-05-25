<?php

declare(strict_types=1);

namespace Runway\DataStorage\Event\PDO;

readonly class PDONotConnectedEnvelope extends PDOErrorEnvelope {
    public function __construct(
        private string $query,
        private array  $vars,
        int            $code = 0
    ) {
        parent::__construct($code, "Database not connected", $query);
    }

    public function getQuery(): string {
        return $this->query;
    }

    public function getVars(): array {
        return $this->vars;
    }
}
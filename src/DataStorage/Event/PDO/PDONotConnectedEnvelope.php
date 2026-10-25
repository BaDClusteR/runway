<?php

declare(strict_types=1);

namespace Runway\DataStorage\Event\PDO;

readonly class PDONotConnectedEnvelope extends PDOErrorEnvelope {
    /**
     * @param array<mixed> $vars
     */
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

    /**
     * @return array<mixed>
     */
    public function getVars(): array {
        return $this->vars;
    }
}
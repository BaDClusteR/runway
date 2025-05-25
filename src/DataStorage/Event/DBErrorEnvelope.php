<?php

declare(strict_types=1);

namespace Runway\DataStorage\Event;

readonly class DBErrorEnvelope extends ADBErrorEnvelope {
    public function __construct(
        int            $code,
        string         $message,
        private string $query,
        private string $queryStateErrorCode = ''
    ) {
        parent::__construct($code, $message);
    }

    public function getSqlStateErrorCode(): string {
        return $this->queryStateErrorCode;
    }

    public function getQuery(): string {
        return $this->query;
    }
}
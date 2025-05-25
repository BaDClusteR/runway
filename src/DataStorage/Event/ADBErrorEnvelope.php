<?php

declare(strict_types=1);

namespace Runway\DataStorage\Event;

abstract readonly class ADBErrorEnvelope {
    public function __construct(
        private int    $code,
        private string $message
    ) {}

    public function getCode(): int {
        return $this->code;
    }

    public function getMessage(): string {
        return $this->message;
    }
}
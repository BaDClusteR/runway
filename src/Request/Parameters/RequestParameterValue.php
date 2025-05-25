<?php

declare(strict_types=1);

namespace Runway\Request\Parameters;

readonly class RequestParameterValue implements IRequestParameterValue {
    public function __construct(
        private mixed $rawValue
    ) {}

    public function asInt(): int {
        return (int)$this->rawValue;
    }

    public function asString(): string {
        return (string)$this->rawValue;
    }

    public function asBool(): bool {
        return (bool)$this->rawValue;
    }

    public function asArray(): array {
        return (array)$this->rawValue;
    }

    public function isNull(): bool {
        return $this->rawValue === null;
    }

    public function asRaw(): mixed {
        return $this->rawValue;
    }
}
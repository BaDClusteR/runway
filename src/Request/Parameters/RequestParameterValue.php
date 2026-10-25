<?php

declare(strict_types=1);

namespace Runway\Request\Parameters;

/**
 * A value of the request (a query or a form parameter, etc.). The client decides its type: "?id[]=1" gives an array
 * where a string is expected, so an array is 0 / "" rather than 1 / "Array" with a warning.
 */
readonly class RequestParameterValue implements IRequestParameterValue {
    public function __construct(
        private mixed $rawValue
    ) {}

    public function asInt(): int {
        return is_scalar($this->rawValue)
            ? (int)$this->rawValue
            : 0;
    }

    public function asString(): string {
        return is_scalar($this->rawValue)
            ? (string)$this->rawValue
            : '';
    }

    public function asBool(): bool {
        return (bool)$this->rawValue;
    }

    /**
     * @return array<mixed>
     */
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

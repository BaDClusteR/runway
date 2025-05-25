<?php

namespace Runway\Request\Parameters;

interface IRequestParameterValue {
    public function asInt(): int;

    public function asString(): string;

    public function asBool(): bool;

    public function asArray(): array;

    public function isNull(): bool;

    public function asRaw(): mixed;
}
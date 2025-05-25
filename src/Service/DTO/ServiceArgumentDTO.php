<?php

declare(strict_types=1);

namespace Runway\Service\DTO;

readonly class ServiceArgumentDTO {
    public function __construct(
        private string $name,
        private mixed  $value,
    ) {}

    public function getName(): string {
        return $this->name;
    }

    public function getValue(): mixed {
        return $this->value;
    }
}
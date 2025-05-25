<?php

declare(strict_types=1);

namespace Runway\Service\DTO;

readonly class ParameterDTO {
    public function __construct(
        public string $name,
        public mixed  $value
    ) {}
}
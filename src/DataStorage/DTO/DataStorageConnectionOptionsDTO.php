<?php

declare(strict_types=1);

namespace Runway\DataStorage\DTO;

readonly class DataStorageConnectionOptionsDTO {
    public function __construct(
        public string $dsn,
        public ?string $user = null,
        public ?string $password = null,
        public string $tableNamePrefix = '',
    ) {}
}
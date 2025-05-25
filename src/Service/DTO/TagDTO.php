<?php

declare(strict_types=1);

namespace Runway\Service\DTO;

readonly class TagDTO {
    /**
     * @param string               $name
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private string $name,
        private array  $extra
    ) {}

    public function getName(): string {
        return $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array {
        return $this->extra;
    }
}
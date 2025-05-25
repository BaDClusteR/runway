<?php

declare(strict_types=1);

namespace Runway\Request\Parameters\DTO;

readonly class CookieDTO {
    public function __construct(
        private string $name,
        private string $value,
        private int    $expires,
        private string $path,
        private string $domain,
        private bool   $isSecure,
        private bool   $isHttponly,
    ) {}

    public function getName(): string {
        return $this->name;
    }

    public function getValue(): string {
        return $this->value;
    }

    public function getExpires(): int {
        return $this->expires;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function getDomain(): string {
        return $this->domain;
    }

    public function isSecure(): bool {
        return $this->isSecure;
    }

    public function isHttponly(): bool {
        return $this->isHttponly;
    }
}
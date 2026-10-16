<?php

declare(strict_types=1);

namespace Runway\Request\Parameters\DTO;

readonly class CookieDTO {
    /**
     * @param int    $expires  Unix timestamp, 0 for a session cookie (a past one deletes the cookie).
     * @param string $sameSite "Strict", "Lax" or "None": whether the browser sends the cookie with the requests of
     *                         other sites.
     */
    public function __construct(
        private string $name,
        private string $value,
        private int    $expires,
        private string $path,
        private string $domain,
        private bool   $isSecure,
        private bool   $isHttponly,
        private string $sameSite = 'Lax',
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

    public function getSameSite(): string {
        return $this->sameSite;
    }
}

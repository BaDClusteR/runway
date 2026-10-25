<?php

declare(strict_types=1);

namespace Runway\Request\Parameters\DTO;

readonly class CookieDTO {
    /**
     * @param int                   $expires  Unix timestamp, 0 for a session cookie (a past one deletes the cookie).
     * @param 'Strict'|'Lax'|'None' $sameSite Whether the browser sends the cookie with the requests of other sites.
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

    /**
     * @return 'Strict'|'Lax'|'None'
     */
    public function getSameSite(): string {
        return $this->sameSite;
    }
}

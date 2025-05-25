<?php

declare(strict_types=1);

namespace Runway\Request;

use Runway\Request\Parameters\DTO\CookieDTO;

class Response implements IResponse {
    public const string CONTENT_LENGTH_HEADER = 'Content-Length';

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $cookies
     */
    public function __construct(
        private int    $code = 0,
        private string $body = '',
        private array  $headers = [],
        private array  $cookies = [],
    ) {}

    public function getBody(): string {
        return $this->body;
    }

    public function setBody(string $body): static {
        $this->body = $body;

        return $this;
    }

    public function getHeaders(): array {
        return $this->addDefaultContentLengthIfNeeded(
            $this->headers
        );
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    protected function addDefaultContentLengthIfNeeded(array $headers): array {
        $headers[static::CONTENT_LENGTH_HEADER] ??= strlen($this->body);

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): static {
        $this->headers = $headers;

        return $this;
    }

    public function addHeader(string $name, string $value): static {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @param array<string, string> $headers
     */
    public function addHeaders(array $headers): static {
        $this->headers = [...$this->headers, ...$headers];

        return $this;
    }

    public function getCookies(): array {
        return $this->cookies;
    }

    public function setCookies(array $cookies): static {
        $this->cookies = $cookies;

        return $this;
    }

    public function addCookie(CookieDTO $cookie): static {
        $this->cookies[] = $cookie;

        return $this;
    }

    public function getCode(): int {
        return $this->code;
    }

    public function setCode(int $code): static {
        $this->code = $code;

        return $this;
    }
}
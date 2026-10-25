<?php

declare(strict_types=1);

namespace Runway\Tests\Support;

final readonly class HttpResponse {
    /**
     * @param array<string, string[]> $headers Lower case name => values.
     */
    public function __construct(
        public int    $code,
        public array  $headers,
        public string $body
    ) {}

    public function getHeader(string $name): ?string {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * @return array<mixed>
     */
    public function getJson(): array {
        $data = json_decode($this->body, true);

        return is_array($data) ? $data : [];
    }
}

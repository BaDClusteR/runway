<?php

declare(strict_types=1);

namespace Runway\Request\Parameters\Provider;

use Runway\Request\Parameters\DataBuilder\IFileParametersDataBuilder;
use Runway\Request\Parameters\DTO\FileDTO;

readonly class RequestParametersProvider implements IRequestParametersProvider {
    public function __construct(
        private IFileParametersDataBuilder $fileParametersDataBuilder,
    ) {}

    public function getMethod(): string {
        return $_SERVER['REQUEST_METHOD'] ?? '';
    }

    public function getHost(): string {
        return $_SERVER['HTTP_HOST'] ?? '';
    }

    public function getPort(): int {
        return (int)($_SERVER['SERVER_PORT'] ?? 0);
    }

    public function getIpAddress(): string {
        if ($this->isCLI()) {
            return "127.0.0.1";
        }

        return array_reduce(
            ["HTTP_FASTLY_CLIENT_IP", "HTTP_X_FORWARDED_FOR", "REMOTE_ADDR"],
            static function (string $carry, string $item): string {
                if ($carry) {
                    return $carry;
                }

                return (string)($_SERVER[$item] ?? "");
            },
            ""
        );
    }

    protected function isCLI(): bool {
        return PHP_SAPI === 'cli';
    }

    public function getProtocol(): string {
        return empty($_SERVER['HTTPS']) ? 'http' : 'https';
    }

    public function getPath(): string {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    }

    public function getRequestUri(): string {
        return $_SERVER['REQUEST_URI'] ?? '';
    }

    public function getBody(): string {
        return file_get_contents('php://input');
    }

    /**
     * @return array<string, mixed>
     */
    public function getGetParameters(): array {
        return $_GET;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPostParameters(): array {
        return $_POST;
    }

    /**
     * @return array<string, mixed>
     */
    public function getServerParameters(): array {
        return $_SERVER;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array {
        $result = [];

        foreach (getallheaders() as $name => $value) {
            $result[strtolower($name)] = $value;
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    public function getCookies(): array {
        return $_COOKIE;
    }

    /**
     * @return string[]
     */
    public function getArguments(): array {
        return $_SERVER['argv'] ?? [];
    }

    /**
     * @return FileDTO[]
     */
    public function getFiles(): array {
        $result = [];

        foreach ($_FILES as $index => $file) {
            $result[$index] = $this->fileParametersDataBuilder->buildFileParameter($file);
        }

        return $result;
    }
}
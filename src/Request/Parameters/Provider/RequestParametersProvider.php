<?php

declare(strict_types=1);

namespace Runway\Request\Parameters\Provider;

use Runway\Request\Parameters\DataBuilder\IFileParametersDataBuilder;
use Runway\Request\Parameters\DTO\FileDTO;

readonly class RequestParametersProvider implements IRequestParametersProvider {
    /**
     * @param string|null $trustedProxies Comma-separated IP addresses of the reverse proxies (e.g. a load balancer):
     *                                    only their X-Forwarded-For header is trusted, see getIpAddress().
     */
    public function __construct(
        private IFileParametersDataBuilder $fileParametersDataBuilder,
        private ?string                    $trustedProxies = null
    ) {}

    public function getMethod(): string {
        return $this->getServerString('REQUEST_METHOD');
    }

    public function getHost(): string {
        return $this->getServerString('HTTP_HOST');
    }

    public function getPort(): int {
        return (int)$this->getServerString('SERVER_PORT');
    }

    public function getIpAddress(): string {
        if ($this->isCLI()) {
            return "127.0.0.1";
        }

        $remoteAddress = $this->getServerString('REMOTE_ADDR');
        $trustedProxies = $this->getTrustedProxies();

        // Anyone can send X-Forwarded-For: it's trusted only if the request came from a known proxy.
        if (!in_array($remoteAddress, $trustedProxies, true)) {
            return $remoteAddress;
        }

        // "client, proxy1, proxy2": every proxy appends the address it got the request from, so the client is the
        // last address that is not a trusted proxy (the ones before it could be forged by the client).
        $forwardedFor = array_map('trim', explode(',', $this->getServerString('HTTP_X_FORWARDED_FOR')));

        foreach (array_reverse($forwardedFor) as $address) {
            if ($address !== '' && !in_array($address, $trustedProxies, true)) {
                return $address;
            }
        }

        return $remoteAddress;
    }

    /**
     * @return string[]
     */
    protected function getTrustedProxies(): array {
        return array_values(array_filter(
            array_map('trim', explode(',', (string)$this->trustedProxies)),
            static fn(string $address): bool => $address !== ''
        ));
    }

    protected function getServerString(string $key): string {
        $value = $_SERVER[$key] ?? '';

        return is_scalar($value) ? (string)$value : '';
    }

    protected function isCLI(): bool {
        return PHP_SAPI === 'cli';
    }

    public function getProtocol(): string {
        return empty($_SERVER['HTTPS']) ? 'http' : 'https';
    }

    public function getPath(): string {
        // Null without a path, false for a malformed URI (e.g. "//:80"): both are the root, not a TypeError.
        return parse_url($this->getRequestUri(), PHP_URL_PATH) ?: '/';
    }

    public function getRequestUri(): string {
        return $this->getServerString('REQUEST_URI');
    }

    public function getBody(): string {
        return (string)@file_get_contents('php://input');
    }

    /**
     * @return array<mixed>
     */
    public function getGetParameters(): array {
        return $_GET;
    }

    /**
     * @return array<mixed>
     */
    public function getPostParameters(): array {
        return $_POST;
    }

    /**
     * @return array<mixed>
     */
    public function getServerParameters(): array {
        return $_SERVER;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array {
        $result = [];

        if (!function_exists('getallheaders')) {
            // CLI SAPI: the headers of $_SERVER (usually there are none)
            foreach ($_SERVER as $name => $value) {
                if (is_string($name) && str_starts_with($name, 'HTTP_') && is_scalar($value)) {
                    $header = str_replace('_', '-', substr($name, 5));
                    $result[strtolower($header)] = (string)$value;
                }
            }

            return $result;
        }

        foreach (getallheaders() as $name => $value) {
            if (is_scalar($value)) {
                $result[strtolower((string)$name)] = (string)$value;
            }
        }

        return $result;
    }

    /**
     * @return array<mixed> A value is an array for "name[key]=value".
     */
    public function getCookies(): array {
        return $_COOKIE;
    }

    /**
     * @return string[]
     */
    public function getArguments(): array {
        $arguments = $_SERVER['argv'] ?? [];

        return is_array($arguments)
            ? array_values(array_filter($arguments, 'is_string'))
            : [];
    }

    /**
     * A field of several files ("photos[]") is split into the fields of the files: "photos[0]", "photos[1]" and so
     * on (the nested ones too: "photos[a][0]").
     *
     * @return array<string, FileDTO> Field => file.
     */
    public function getFiles(): array {
        $result = [];

        foreach ($_FILES as $field => $file) {
            if (is_array($file)) {
                $this->addFiles($result, (string)$field, $file);
            }
        }

        return $result;
    }

    /**
     * PHP gives the files of a field as separate arrays of their names, sizes and so on.
     *
     * @param array<string, FileDTO> $result
     * @param array<mixed>           $file
     */
    private function addFiles(array &$result, string $field, array $file): void {
        if (!is_array($file['name'] ?? null)) {
            $result[$field] = $this->fileParametersDataBuilder->buildFileParameter($file);

            return;
        }

        foreach (array_keys($file['name']) as $key) {
            $this->addFiles(
                $result,
                "{$field}[$key]",
                array_map(static fn(mixed $values): mixed => is_array($values) ? ($values[$key] ?? null) : null, $file)
            );
        }
    }
}

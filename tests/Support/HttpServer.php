<?php

declare(strict_types=1);

namespace Runway\Tests\Support;

use RuntimeException;

/**
 * The PHP built-in server with the front controller of the E2E tests (tests/E2E/server.php) and its environment, in a
 * temporary folder (the logs are there too).
 */
final class HttpServer {
    use TemporaryDirectory;

    /** @var resource|null */
    private $process = null;

    private int $port = 0;

    /**
     * @param array<string, string> $env The variables of the .env file of the server.
     */
    public function start(array $env): void {
        $root = $this->createTemporaryDirectory();
        file_put_contents(
            "$root/e2e.env",
            implode("\n", array_map(static fn(string $name, string $value): string => "$name=$value", array_keys($env), $env))
        );
        $this->port = $this->getFreePort();

        $process = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$this->port", dirname(__DIR__) . '/E2E/server.php'],
            [
                0 => ['pipe', 'r'],
                1 => ['file', "$root/server.log", 'a'],
                2 => ['file', "$root/server.log", 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
            [...getenv(), 'RUNWAY_E2E_ROOT' => $root, 'RUNWAY_E2E_ENV_FILE' => "$root/e2e.env"]
        );

        if ($process === false) {
            throw new RuntimeException('Cannot start the PHP built-in server.');
        }

        $this->process = $process;
        $this->waitUntilReady();
    }

    public function stop(): void {
        if ($this->process !== null) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }

        $this->removeTemporaryDirectory();
    }

    public function getBaseUrl(): string {
        return "http://127.0.0.1:$this->port";
    }

    /**
     * @return string The log of the server (e.g. for a failed test).
     */
    public function getLog(): string {
        return (string)@file_get_contents("$this->directory/server.log");
    }

    /**
     * @return string The log of the application (Logger) of today.
     */
    public function getApplicationLog(): string {
        return (string)@file_get_contents("$this->directory/log/" . date('Y/m/Y-m-d') . '.log');
    }

    private function getFreePort(): int {
        $socket = stream_socket_server('tcp://127.0.0.1:0');

        if ($socket === false) {
            throw new RuntimeException('Cannot find a free port.');
        }

        $name = (string)stream_socket_get_name($socket, false);
        fclose($socket);

        return (int)substr($name, (int)strrpos($name, ':') + 1);
    }

    private function waitUntilReady(): void {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', $this->port, $errorCode, $errorMessage, 0.2);

            if ($connection !== false) {
                fclose($connection);

                return;
            }

            usleep(50_000);
        }

        throw new RuntimeException("The PHP built-in server has not started:\n" . $this->getLog());
    }
}

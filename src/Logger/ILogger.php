<?php

namespace Runway\Logger;

use Runway\Logger\Enum\LogLevelEnum;

interface ILogger {
    public function setLogLevel(LogLevelEnum $level): static;

    /**
     * @param array<mixed> $context Encoded as JSON.
     */
    public function debug(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context Encoded as JSON.
     */
    public function info(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context Encoded as JSON.
     */
    public function deprecated(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context Encoded as JSON.
     */
    public function warning(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context Encoded as JSON.
     */
    public function error(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context Encoded as JSON.
     */
    public function critical(string $message, array $context = []): void;

    /**
     * @param array<mixed> $context Encoded as JSON.
     */
    public function emergency(string $message, array $context = []): void;
}
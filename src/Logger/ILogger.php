<?php

namespace Runway\Logger;

use Runway\Logger\Enum\LogLevelEnum;

interface ILogger {
    public function setLogLevel(LogLevelEnum $level): static;

    public function debug(string $message, array $context = []): void;

    public function info(string $message, array $context = []): void;

    public function deprecated(string $message, array $context = []): void;

    public function warning(string $message, array $context = []): void;

    public function error(string $message, array $context = []): void;

    public function critical(string $message, array $context = []): void;

    public function emergency(string $message, array $context = []): void;
}
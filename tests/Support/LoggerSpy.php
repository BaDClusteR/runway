<?php

declare(strict_types=1);

namespace Runway\Tests\Support;

use Runway\Logger\Enum\LogLevelEnum;
use Runway\Logger\ILogger;

/**
 * Records the log entries instead of writing them.
 */
final class LoggerSpy implements ILogger {
    /**
     * @var list<array{level: string, message: string, context: array<mixed>}>
     */
    public array $records = [];

    public function setLogLevel(LogLevelEnum $level): static {
        return $this;
    }

    public function debug(string $message, array $context = []): void {
        $this->record('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->record('info', $message, $context);
    }

    public function deprecated(string $message, array $context = []): void {
        $this->record('deprecated', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->record('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->record('error', $message, $context);
    }

    public function critical(string $message, array $context = []): void {
        $this->record('critical', $message, $context);
    }

    public function emergency(string $message, array $context = []): void {
        $this->record('emergency', $message, $context);
    }

    /**
     * @return string[] "level: message" of every entry.
     */
    public function getMessages(): array {
        return array_map(static fn(array $record): string => "{$record['level']}: {$record['message']}", $this->records);
    }

    /**
     * @param array<mixed> $context
     */
    private function record(string $level, string $message, array $context): void {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
}

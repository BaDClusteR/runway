<?php

declare(strict_types=1);

namespace Runway\Logger;

use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\FileSystem\Exception\FileSystemException;
use Runway\FileSystem\IFileSystem;
use Runway\Logger\Enum\LogLevelEnum;
use Runway\Logger\Exception\LoggerException;
use Runway\Request\IRequest;
use Runway\Singleton\Container;
use JsonException;

class Logger implements ILogger {
    protected LogLevelEnum $logLevel;

    public function __construct(
        protected IFileSystem           $fileSystem,
        protected IEnvVariablesProvider $envVarProvider,
        protected string                $logSubDir
    ) {
        $this->logLevel = $this->getLogLevelFromEnvVar();
    }

    public function setLogLevel(LogLevelEnum $level): static {
        $this->logLevel = $level;

        return $this;
    }

    public function debug(string $message, array $context = []): void {
        $this->record($message, LogLevelEnum::DEBUG, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->record($message, LogLevelEnum::INFO, $context);
    }

    public function deprecated(string $message, array $context = []): void {
        $this->record($message, LogLevelEnum::DEPRECATED, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->record($message, LogLevelEnum::WARNING, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->record($message, LogLevelEnum::ERROR, $context);
    }

    public function critical(string $message, array $context = []): void {
        $this->record($message, LogLevelEnum::CRITICAL, $context);
    }

    public function emergency(string $message, array $context = []): void {
        $this->record($message, LogLevelEnum::EMERGENCY, $context);
    }

    protected function record(string $message, LogLevelEnum $logLevelEnum, array $context): void {
        if ($this->getLogLevelAsInt($logLevelEnum) < $this->getLogLevelAsInt($this->logLevel)) {
            return;
        }

        try {
            $rawData = json_encode(
                array_merge(
                    [
                        'log_level' => $this->getLogLevelAsString($logLevelEnum),
                        'message'   => $message,
                        'context'   => $context,
                    ],
                    $this->getRecordExtraInfo()
                ),
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            throw new LoggerException(
                "Cannot encode log entry to JSON: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }

        try {
            $this->fileSystem->append(
                $this->getLogPath(),
                $rawData . "\n"
            );
        } catch (FileSystemException $e) {
            throw new LoggerException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    protected function getLogDir(): string {
        return $this->getLogRootDir() . "/" . $this->logSubDir . "/" . date("Y") . "/" . date("m");
    }

    protected function getLogPath(): string {
        return $this->getLogDir() . "/" . date("Y-m-d") . ".log";
    }

    protected function getLogLevelAsString(LogLevelEnum $level): string {
        return match ($level) {
            LogLevelEnum::DEBUG      => "DEBUG",
            LogLevelEnum::INFO       => "INFO",
            LogLevelEnum::DEPRECATED => "DEPRECATED",
            LogLevelEnum::WARNING    => "WARNING",
            LogLevelEnum::ERROR      => "ERROR",
            LogLevelEnum::CRITICAL   => "CRITICAL",
            LogLevelEnum::EMERGENCY  => "EMERGENCY"
        };
    }

    protected function getRecordExtraInfo(): array {
        $now = time();
        $request = $this->getRequest();

        $result = [
            'time'      => date('Y-m-d H:i:s', $now),
            'ip'        => $request->getIpAddress(),
            'timestamp' => $now,
            'requestId' => $request->getRequestId(),
            'trace'     => $this->getStackTrace()
        ];

        if ($request->isCLI()) {
            $result['type'] = 'CLI';
        } else {
            $result['url'] = $request->getRequestUri();
            $result['user_agent'] = $request->getUserAgent();
        }

        return $result;
    }

    protected function getLogLevelFromEnvVar(): LogLevelEnum {
        $level = (int)$this->envVarProvider->getEnvVariable("LOG_LEVEL");

        return match ($level) {
            1       => LogLevelEnum::INFO,
            2       => LogLevelEnum::DEPRECATED,
            3       => LogLevelEnum::WARNING,
            4       => LogLevelEnum::ERROR,
            5       => LogLevelEnum::CRITICAL,
            6       => LogLevelEnum::EMERGENCY,
            default => LogLevelEnum::DEBUG
        };
    }

    protected function getLogLevelAsInt(LogLevelEnum $level): int {
        return match ($level) {
            LogLevelEnum::INFO       => 1,
            LogLevelEnum::DEPRECATED => 2,
            LogLevelEnum::WARNING    => 3,
            LogLevelEnum::ERROR      => 4,
            LogLevelEnum::CRITICAL   => 5,
            LogLevelEnum::EMERGENCY  => 6,
            default                  => 0
        };
    }

    protected function getStackTrace(): array {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

        while (($trace[0]['file'] ?? '') === __FILE__) {
            array_shift($trace);
        }

        return $trace;
    }

    protected function getRequest(): IRequest {
        return Container::getInstance()->getService(IRequest::class);
    }

    protected function getLogRootDir(): string {
        return defined("PROJECT_ROOT")
            ? constant("PROJECT_ROOT")
            : RUNWAY_ROOT;
    }
}
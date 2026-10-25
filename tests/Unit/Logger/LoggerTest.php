<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Logger;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\FileSystem\Exception\CannotOpenFileException;
use Runway\FileSystem\IFileSystem;
use Runway\Logger\Enum\LogLevelEnum;
use Runway\Logger\Exception\LoggerException;
use Runway\Logger\Logger;
use Runway\Tests\Support\EnvStub;

class LoggerTest extends TestCase {
    /**
     * @var list<array{string, mixed}> Path and data of the appended records.
     */
    protected array $appended = [];

    public function testRecord(): void {
        $this->logger(['LOG_LEVEL' => 0])->error('Something "failed" ✓', ['id' => 42, 'bad' => "\xB1"]);

        self::assertCount(1, $this->appended);
        [$path, $data] = $this->appended[0];

        self::assertSame(PROJECT_ROOT . '/logs/' . date('Y') . '/' . date('m') . '/' . date('Y-m-d') . '.log', $path);
        self::assertIsString($data);
        self::assertStringEndsWith("\n", $data);
        // Not escaped Unicode, the malformed UTF-8 is replaced.
        self::assertStringContainsString('"message":"Something \"failed\" ✓"', $data);

        $record = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertSame('ERROR', $record['log_level']);
        self::assertSame(['id' => 42, 'bad' => "\u{FFFD}"], $record['context']);
        self::assertSame('CLI', $record['type']);
        self::assertSame('127.0.0.1', $record['ip']);
        self::assertIsInt($record['timestamp']);
        self::assertSame(date(DATE_ATOM, $record['timestamp']), $record['time']);
        self::assertIsString($record['requestId']);
        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $record['requestId']);
        self::assertIsArray($record['trace']);
        self::assertIsArray($record['trace'][0]);
        // The trace starts outside of the logger.
        self::assertSame(__FILE__, $record['trace'][0]['file']);
        self::assertArrayNotHasKey('url', $record);
    }

    /**
     * @return iterable<string, array{mixed, list<string>}>
     */
    public static function logLevelProvider(): iterable {
        $all = ['DEBUG', 'INFO', 'DEPRECATED', 'WARNING', 'ERROR', 'CRITICAL', 'EMERGENCY'];

        yield 'no level' => [null, $all];
        yield 'debug' => [0, $all];
        yield 'info' => [1, array_slice($all, 1)];
        yield 'deprecated' => [2, array_slice($all, 2)];
        yield 'warning' => ['3', array_slice($all, 3)];
        yield 'error' => [4, array_slice($all, 4)];
        yield 'critical' => [5, array_slice($all, 5)];
        yield 'emergency' => [6, array_slice($all, 6)];
        yield 'unknown' => [7, $all];
        yield 'not a number' => ['high', $all];
    }

    /**
     * @param list<string> $expectedLevels
     */
    #[DataProvider('logLevelProvider')]
    public function testLogLevelOfEnvironment(mixed $level, array $expectedLevels): void {
        $logger = $this->logger(['LOG_LEVEL' => $level]);

        $logger->debug('debug');
        $logger->info('info');
        $logger->deprecated('deprecated');
        $logger->warning('warning');
        $logger->error('error');
        $logger->critical('critical');
        $logger->emergency('emergency');

        self::assertSame($expectedLevels, $this->getLevels());
    }

    public function testSetLogLevel(): void {
        $logger = $this->logger(['LOG_LEVEL' => 0]);

        self::assertSame($logger, $logger->setLogLevel(LogLevelEnum::CRITICAL));
        $logger->error('error');
        $logger->critical('critical');

        self::assertSame(['CRITICAL'], $this->getLevels());
    }

    public function testFileSystemError(): void {
        $fileSystem = $this->createStub(IFileSystem::class);
        $fileSystem->method('append')->willThrowException(new CannotOpenFileException('/log', 5));

        $this->expectException(LoggerException::class);
        $this->expectExceptionMessage("Cannot create '/log'");
        $this->expectExceptionCode(5);

        new Logger($fileSystem, new EnvStub(), 'logs')->error('error');
    }

    public function testJsonError(): void {
        $this->expectException(LoggerException::class);
        $this->expectExceptionMessage('Cannot encode log entry to JSON: ');

        $this->logger()->error('error', ['infinity' => INF]);
    }

    /**
     * @param array<string, mixed> $env
     */
    protected function logger(array $env = []): Logger {
        $fileSystem = $this->createStub(IFileSystem::class);
        $fileSystem->method('append')->willReturnCallback(function (string $path, mixed $data): void {
            $this->appended[] = [$path, $data];
        });

        return new Logger($fileSystem, new EnvStub($env), 'logs');
    }

    /**
     * @return list<string>
     */
    protected function getLevels(): array {
        return array_map(static function (array $record): string {
            $data = json_decode(is_string($record[1]) ? $record[1] : '', true);

            return is_array($data) && is_string($data['log_level'] ?? null) ? $data['log_level'] : '';
        }, $this->appended);
    }
}

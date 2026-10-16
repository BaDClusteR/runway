<?php

declare(strict_types=1);

namespace Runway\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Runway\Controller\ErrorController;
use Runway\Controller\ExceptionController;
use Runway\Logger\ILogger;
use Runway\Singleton\IKernel;
use RuntimeException;

class ErrorControllersTest extends TestCase {
    public function testExceptionDetailsAreHiddenInProduction(): void {
        $response = new ExceptionController($this->kernel(false), $this->createStub(ILogger::class))
            ->run(new RuntimeException('SELECT * FROM secrets'));

        self::assertSame(500, $response->getCode());
        self::assertSame('500 error', $response->getBody());
    }

    public function testExceptionDetailsAreShownInDebugMode(): void {
        $response = new ExceptionController($this->kernel(true), $this->createStub(ILogger::class))
            ->run(new RuntimeException('SELECT * FROM secrets'));

        self::assertStringContainsString('SELECT * FROM secrets', $response->getBody());
    }

    public function testExceptionIsLoggedWithoutArguments(): void {
        $logger = $this->createMock(ILogger::class);
        $logger->expects($this->once())
               ->method('error')
               ->with(
                   $this->stringContains('SELECT * FROM secrets'),
                   $this->callback(static fn(array $context): bool => is_string($context['trace']))
               );

        new ExceptionController($this->kernel(false), $logger)->run(new RuntimeException('SELECT * FROM secrets'));
    }

    public function testErrorDetailsAreHiddenInProduction(): void {
        self::assertSame('500 error', new ErrorController($this->kernel(false))->run(1, 'error', '/app/file.php', 42)->getBody());
        self::assertStringContainsString('/app/file.php', new ErrorController($this->kernel(true))->run(1, 'error', '/app/file.php', 42)->getBody());
    }

    protected function kernel(bool $isDebugMode): IKernel {
        $kernel = $this->createStub(IKernel::class);
        $kernel->method('isDebugMode')->willReturn($isDebugMode);

        return $kernel;
    }
}

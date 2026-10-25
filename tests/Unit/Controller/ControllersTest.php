<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Runway\Controller\Controller404;
use Runway\Controller\ErrorController;
use Runway\Controller\ExceptionController;
use Runway\Controller\MethodNotAllowedController;
use Runway\Exception\FatalErrorException;
use Runway\Request\IRequestRead;
use Runway\Singleton\IKernel;
use Runway\Tests\Support\ContainerState;
use Runway\Tests\Support\LoggerSpy;

class ControllersTest extends TestCase {
    public function testNotFound(): void {
        $response = new Controller404()->run();

        self::assertSame(404, $response->getCode());
        self::assertSame('404 error', $response->getBody());
    }

    public function testMethodNotAllowed(): void {
        $response = $this->methodNotAllowed('POST')->run(['GET', 'HEAD', 'GET']);

        self::assertSame(405, $response->getCode());
        self::assertSame('405 error', $response->getBody());
        self::assertSame('GET, HEAD, OPTIONS', $response->getHeaders()['Allow']);
    }

    public function testOptionsGetsAllowedMethods(): void {
        $response = $this->methodNotAllowed('options')->run(['PATCH', 'OPTIONS']);

        self::assertSame(204, $response->getCode());
        self::assertSame('', $response->getBody());
        self::assertSame('PATCH, OPTIONS', $response->getHeaders()['Allow']);
    }

    public function testErrorMessageInDebugMode(): void {
        $response = new ErrorController($this->kernel(true))->run(E_USER_ERROR, 'Oops', '/app/a.php', 42);

        self::assertSame(500, $response->getCode());
        self::assertSame('Error in /app/a.php on line 42: Oops', $response->getBody());
    }

    public function testFatalErrorGoesToErrorController(): void {
        ContainerState::reset();
        $logger = new LoggerSpy();

        // The error controller of the container: the kernel there is not in the debug mode.
        $response = new ExceptionController($this->kernel(true), $logger)
            ->run(new FatalErrorException(E_USER_ERROR, 'Oops', '/app/a.php', 42));

        self::assertSame(500, $response->getCode());
        self::assertSame('500 error', $response->getBody());
        // The error handler has logged it already.
        self::assertSame([], $logger->records);
    }

    public function testExceptionIsLoggedWithDetails(): void {
        $logger = new LoggerSpy();
        $exception = new \LogicException('Wrong');

        $response = new ExceptionController($this->kernel(true), $logger)->run($exception);

        self::assertSame("Exception in {$exception->getFile()} on line {$exception->getLine()}: Wrong", $response->getBody());
        self::assertSame(['error: Uncaught exception: Wrong'], $logger->getMessages());
        self::assertSame(\LogicException::class, $logger->records[0]['context']['exception']);
        self::assertSame(__FILE__, $logger->records[0]['context']['file']);
        self::assertSame($exception->getLine(), $logger->records[0]['context']['line']);
    }

    protected function methodNotAllowed(string $method): MethodNotAllowedController {
        $request = $this->createStub(IRequestRead::class);
        $request->method('getMethod')->willReturn($method);

        return new MethodNotAllowedController($request);
    }

    protected function kernel(bool $isDebugMode): IKernel {
        $kernel = $this->createStub(IKernel::class);
        $kernel->method('isDebugMode')->willReturn($isDebugMode);

        return $kernel;
    }
}

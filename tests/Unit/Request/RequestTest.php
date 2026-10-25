<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Request;

use PHPUnit\Framework\TestCase;
use Runway\Exception\FatalErrorException;
use Runway\Request\Parameters\DTO\FileDTO;
use Runway\Request\Parameters\Provider\IRequestParametersProvider;
use Runway\Request\Request;
use Runway\Router\DTO\RouteMatchDTO;
use Runway\Service\DTO\RouteDTO;
use Runway\Tests\Support\ContainerState;
use Runway\Tests\Support\LoggerSpy;

class RequestTest extends TestCase {
    protected LoggerSpy $logger;

    protected function setUp(): void {
        $this->logger = new LoggerSpy();
    }

    public function testParametersOfProvider(): void {
        $file = new FileDTO('a.txt', 'text/plain', 3, '/tmp/a', '0', 'a.txt');
        $request = $this->request([
            'getBody'             => 'body',
            'getHeaders'          => ['x-test' => 'yes', 'x-zero' => '0', 'x-empty' => ''],
            'getArguments'        => ['console', 'run'],
            'getFiles'            => ['file' => $file],
            'getCookies'          => ['session' => 'abc', 'zero' => '0', 'empty' => '', 'list' => ['a']],
            'getMethod'           => 'POST',
            'getPath'             => '/items',
            'getRequestUri'       => '/items?page=2',
            'getGetParameters'    => ['page' => '2', 'null' => null],
            'getPostParameters'   => ['name' => 'Bob'],
            'getServerParameters' => ['SERVER_NAME' => 'example.com'],
            'getHost'             => 'example.com',
            'getProtocol'         => 'https',
            'getIpAddress'        => '10.0.0.1',
            'getPort'             => 443,
        ]);

        self::assertSame('body', $request->getBody());
        self::assertSame('yes', $request->getHeader('X-Test'));
        self::assertSame('0', $request->getHeader('x-zero'));
        self::assertNull($request->getHeader('x-empty'));
        self::assertNull($request->getHeader('x-missing'));
        self::assertSame(['console', 'run'], $request->getArguments());
        self::assertSame($file, $request->getFile('file'));
        self::assertNull($request->getFile('missing'));
        self::assertSame(['file' => $file], $request->getFiles());
        self::assertSame('abc', $request->getCookie('session'));
        self::assertSame('0', $request->getCookie('zero'));
        self::assertNull($request->getCookie('empty'));
        self::assertNull($request->getCookie('list'));
        self::assertNull($request->getCookie('missing'));
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/items', $request->getPath());
        self::assertSame('/items?page=2', $request->getRequestUri());
        self::assertTrue($request->hasGetParameter('page'));
        self::assertTrue($request->hasGetParameter('null'));
        self::assertFalse($request->hasGetParameter('missing'));
        self::assertSame(2, $request->getGetParameter('page')->asInt());
        self::assertTrue($request->getGetParameter('missing')->isNull());
        self::assertTrue($request->hasPostParameter('name'));
        self::assertFalse($request->hasPostParameter('page'));
        self::assertSame('Bob', $request->getPostParameter('name')->asString());
        self::assertTrue($request->getPostParameter('missing')->isNull());
        self::assertSame('example.com', $request->getServerParameter('SERVER_NAME')->asString());
        self::assertTrue($request->getServerParameter('missing')->isNull());
        self::assertSame(['SERVER_NAME' => 'example.com'], $request->getServerParameters());
        self::assertSame('example.com', $request->getHost());
        self::assertSame('https', $request->getProtocol());
        self::assertSame('10.0.0.1', $request->getIpAddress());
        self::assertSame(443, $request->getPort());
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/', $request->getRequestId());
        self::assertNull($request->getRouteMatch());
        self::assertSame('', $request->getUserAgent());
        self::assertTrue($request->isCLI());
    }

    public function testSetters(): void {
        $file = new FileDTO('a.txt', 'text/plain', 3, '/tmp/a', '0', 'a.txt');
        $match = new RouteMatchDTO(new RouteDTO('home', '/', 'A', 'b', 0, '', ''), []);
        $request = $this->request();

        $request->setMethod('PUT')
                ->setPath('/path')
                ->setRequestUri('/path?a=1')
                ->setHost('host')
                ->setPort(8080)
                ->setProtocol('http')
                ->setIpAddress('1.2.3.4')
                ->setBody('new body')
                ->setGetParameters(['a' => '1'])
                ->setPostParameters(['b' => '2'])
                ->setServerParameters(['c' => '3'])
                ->setHeaders(['User-Agent' => 'Browser'])
                ->setCookies(['d' => '4'])
                ->setFiles(['f' => $file])
                ->setArguments(['arg'])
                ->setRequestId('id')
                ->setRouteMatch($match);

        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/path', $request->getPath());
        self::assertSame('/path?a=1', $request->getRequestUri());
        self::assertSame('host', $request->getHost());
        self::assertSame(8080, $request->getPort());
        self::assertSame('http', $request->getProtocol());
        self::assertSame('1.2.3.4', $request->getIpAddress());
        self::assertSame('new body', $request->getBody());
        self::assertSame('1', $request->getGetParameter('a')->asString());
        self::assertSame('2', $request->getPostParameter('b')->asString());
        self::assertSame('3', $request->getServerParameter('c')->asString());
        self::assertSame('Browser', $request->getUserAgent());
        self::assertSame('Browser', $request->getHeader('user-agent'));
        self::assertSame('4', $request->getCookie('d'));
        self::assertSame($file, $request->getFile('f'));
        self::assertSame(['arg'], $request->getArguments());
        self::assertSame('id', $request->getRequestId());
        self::assertSame($match, $request->getRouteMatch());
    }

    public function testSetHeadersKeepsOtherHeaders(): void {
        $request = $this->request(['getHeaders' => ['a' => '1']]);
        $request->setHeaders(['B' => '2']);

        self::assertSame('1', $request->getHeader('a'));
        self::assertSame('2', $request->getHeader('b'));
    }

    public function testProcessCallsRouter(): void {
        ContainerState::reset();
        $request = $this->request(['getPath' => '/hello', 'getMethod' => 'GET']);

        try {
            $response = $request->process();
        } finally {
            restore_error_handler();
        }

        self::assertSame(200, $response->getCode());
        self::assertSame('Hello', $response->getBody());
    }

    public function testProcessGivesExceptionToExceptionController(): void {
        ContainerState::reset();
        $request = $this->request(['getPath' => '/exception', 'getMethod' => 'GET']);

        try {
            $response = $request->process();
        } finally {
            restore_error_handler();
        }

        self::assertSame(500, $response->getCode());
        self::assertSame('500 error', $response->getBody());
    }

    public function testErrorHandlerLogsAndGoesOn(): void {
        $handler = $this->request()->errorHandler();

        self::assertFalse($this->withErrorReporting(E_ALL, static fn(): bool => $handler(E_USER_WARNING, "warning", "/a.php", 1)));
        self::assertSame(['error: warning'], $this->logger->getMessages());
    }

    public function testErrorHandlerSkipsSuppressedErrors(): void {
        $handler = $this->request()->errorHandler();

        // "@" (or a level without the error).
        self::assertFalse($this->withErrorReporting(0, static fn(): bool => $handler(E_USER_WARNING, "suppressed", "/a.php", 1)));
        self::assertFalse($this->withErrorReporting(E_ALL & ~E_USER_ERROR, static fn(): bool => $handler(E_USER_ERROR, "suppressed", "/a.php", 1)));

        self::assertSame([], $this->logger->records);
    }

    public function testErrorHandlerThrowsFatalErrors(): void {
        $handler = $this->request()->errorHandler();

        foreach ([E_USER_ERROR, E_RECOVERABLE_ERROR] as $errno) {
            try {
                $this->withErrorReporting(E_ALL, static fn(): bool => $handler($errno, "fatal", "/a.php", 7));
                self::fail('No exception');
            } catch (FatalErrorException $e) {
                self::assertSame($errno, $e->getErrno());
                self::assertSame('/a.php', $e->getErrFile());
                self::assertSame(7, $e->getErrLine());
            }
        }

        self::assertSame(['error: fatal', 'error: fatal'], $this->logger->getMessages());
    }

    /**
     * PHPUnit handles the errors of the tests itself: the error reporting of the test is minimal.
     *
     * @param callable(): bool $callback
     */
    protected function withErrorReporting(int $level, callable $callback): bool {
        $errorReporting = error_reporting($level);

        try {
            return $callback();
        } finally {
            error_reporting($errorReporting);
        }
    }

    /**
     * @param array<string, mixed> $parameters The values of the provider methods.
     */
    protected function request(array $parameters = []): TestRequest {
        $provider = $this->createStub(IRequestParametersProvider::class);

        foreach ($parameters as $method => $value) {
            $provider->method($method)->willReturn($value);
        }

        return new TestRequest($provider, $this->logger);
    }
}

final class TestRequest extends Request {
    /**
     * @return callable(int, string, string, int): bool
     */
    public function errorHandler(): callable {
        return $this->getErrorHandler();
    }
}

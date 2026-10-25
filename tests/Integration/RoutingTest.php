<?php

declare(strict_types=1);

namespace Runway\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Request\IResponseRead;
use Runway\Request\Request;
use Runway\Tests\Support\ContainerState;

/**
 * The request of the container through the router to the controllers of tests/config/routes.yaml.
 */
class RoutingTest extends TestCase {
    protected function setUp(): void {
        ContainerState::reset();
    }

    public function testRoute(): void {
        $response = $this->request('GET', '/hello');

        self::assertSame(200, $response->getCode());
        self::assertSame('Hello', $response->getBody());
        self::assertSame(['Content-Type' => 'text/plain', 'Content-Length' => '5'], $response->getHeaders());
        self::assertSame('fixture_hello', Request::getInstance()->getRouteMatch()?->route->name);
    }

    public function testPlaceholders(): void {
        self::assertSame('{"id":"42","method":"DELETE"}', $this->request('DELETE', '/items/42')->getBody());
        self::assertSame('{"id":"7","method":"PATCH"}', $this->request('PATCH', '/items/7')->getBody());
        // A static segment beats a placeholder (it does not match \d+ anyway).
        self::assertSame('search', $this->request('GET', '/items/search')->getBody());
        // The values are decoded and can contain slashes with a requirement.
        self::assertSame('docs/My file.txt', $this->request('GET', '/files/docs/My%20file.txt')->getBody());
    }

    public function testHeadIsGet(): void {
        self::assertSame('Hello', $this->request('HEAD', '/hello')->getBody());
    }

    public function testNotFound(): void {
        $response = $this->request('GET', '/items/abc');

        self::assertSame(404, $response->getCode());
        self::assertSame('404 error', $response->getBody());
    }

    public function testMethodNotAllowed(): void {
        $response = $this->request('POST', '/items/42');

        self::assertSame(405, $response->getCode());
        self::assertSame('GET, DELETE, HEAD, PATCH, OPTIONS', $response->getHeaders()['Allow']);

        $options = $this->request('OPTIONS', '/hello');
        self::assertSame(204, $options->getCode());
        self::assertSame('GET, HEAD, OPTIONS', $options->getHeaders()['Allow']);
    }

    public function testControllerThatIsNotService(): void {
        self::assertSame('plain', $this->request('GET', '/plain')->getBody());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function brokenRouteProvider(): iterable {
        yield 'exception' => ['/exception'];
        yield 'not a response' => ['/not-response'];
        yield 'missing controller' => ['/missing-controller'];
        yield 'missing method' => ['/missing-method'];
        yield 'protected method' => ['/protected-method'];
    }

    #[DataProvider('brokenRouteProvider')]
    public function testErrorsAreHidden(string $path): void {
        $response = $this->request('GET', $path);

        // APP_DEBUG=false in test.env: no messages, paths or SQL.
        self::assertSame(500, $response->getCode());
        self::assertSame('500 error', $response->getBody());
    }

    protected function request(string $method, string $path): IResponseRead {
        $request = Request::getInstance()->setMethod($method)->setPath($path)->setRouteMatch(null);

        try {
            return $request->process();
        } finally {
            // Request::process() sets the error handler of the application.
            restore_error_handler();
        }
    }
}

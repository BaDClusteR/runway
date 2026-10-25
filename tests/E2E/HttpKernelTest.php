<?php

declare(strict_types=1);

namespace Runway\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Runway\Tests\Support\HttpClient;
use Runway\Tests\Support\HttpResponse;
use Runway\Tests\Support\HttpServer;

/**
 * The kernel behind the PHP built-in server: the status codes, the headers and the cookies as a browser gets them,
 * the form fields and the files, the streamed body (see tests/E2E/server.php and tests/config/routes.yaml).
 */
class HttpKernelTest extends TestCase {
    protected static HttpServer $server;

    protected static HttpClient $client;

    public static function setUpBeforeClass(): void {
        static::$server = new HttpServer();
        static::$server->start([
            'APP_DEBUG'       => 'false',
            'LOG_LEVEL'       => '4',
            'TRUSTED_PROXIES' => '',
        ]);
        static::$client = new HttpClient(static::$server->getBaseUrl());
    }

    public static function tearDownAfterClass(): void {
        static::$server->stop();
    }

    public function testResponse(): void {
        $response = $this->request('GET', '/hello');

        self::assertSame(200, $response->code);
        self::assertSame('Hello', $response->body);
        self::assertStringStartsWith('text/plain', (string)$response->getHeader('Content-Type'));
        self::assertSame('5', $response->getHeader('Content-Length'));
    }

    public function testHeadHasNoBody(): void {
        $response = $this->request('HEAD', '/hello');

        self::assertSame(200, $response->code);
        self::assertSame('', $response->body);
    }

    public function testNotFound(): void {
        $response = $this->request('GET', '/missing');

        self::assertSame(404, $response->code);
        self::assertSame('404 error', $response->body);
    }

    public function testMethodNotAllowed(): void {
        $response = $this->request('PUT', '/items/42');

        self::assertSame(405, $response->code);
        self::assertSame('GET, DELETE, HEAD, PATCH, OPTIONS', $response->getHeader('Allow'));

        $options = $this->request('OPTIONS', '/items/42');
        self::assertSame(204, $options->code);
        self::assertSame('GET, DELETE, HEAD, PATCH, OPTIONS', $options->getHeader('Allow'));
    }

    public function testPlaceholders(): void {
        self::assertSame(['id' => '42', 'method' => 'DELETE'], $this->request('DELETE', '/items/42')->getJson());
        self::assertSame('docs/My file.txt', $this->request('GET', '/files/docs/My%20file.txt')->body);
    }

    public function testRequestData(): void {
        $response = static::$client->postForm(
            '/echo?q=search',
            ['name' => 'Bob'],
            [['photos[]', 'a.txt', 'first'], ['photos[]', 'b.txt', 'second']],
            // A client cannot forge its address: there are no trusted proxies.
            ['X-Test' => 'yes', 'Cookie' => 'session=abc', 'X-Forwarded-For' => '1.2.3.4']
        );

        self::assertSame(200, $response->code, static::$server->getLog());
        $data = $response->getJson();
        self::assertSame('POST', $data['method']);
        self::assertSame('/echo', $data['path']);
        self::assertSame('search', $data['query']);
        self::assertSame('Bob', $data['post']);
        self::assertSame('yes', $data['header']);
        self::assertSame('abc', $data['cookie']);
        self::assertSame('127.0.0.1', $data['ip']);
        self::assertSame('fixture_echo', $data['route']);
        self::assertSame(
            [
                'photos[0]' => ['name' => 'a.txt', 'size' => 5, 'content' => 'first'],
                'photos[1]' => ['name' => 'b.txt', 'size' => 6, 'content' => 'second'],
            ],
            $data['files']
        );
    }

    public function testRequestBody(): void {
        $data = $this->request('PATCH', '/echo', '{"a":1}', ['Content-Type' => 'application/json'])->getJson();

        self::assertSame('{"a":1}', $data['body']);
        self::assertSame('PATCH', $data['method']);
        self::assertSame([], $data['files']);
        self::assertNull($data['cookie']);
    }

    public function testCookie(): void {
        $response = $this->request('POST', '/cookie');

        self::assertSame(204, $response->code);
        self::assertSame('session=abc; path=/; HttpOnly; SameSite=Strict', $response->getHeader('Set-Cookie'));
    }

    public function testStreamedResponse(): void {
        $response = $this->request('GET', '/stream');

        self::assertSame(200, $response->code);
        self::assertSame('first,second,third', $response->body);
        self::assertStringStartsWith('text/csv', (string)$response->getHeader('Content-Type'));
        self::assertNull($response->getHeader('Content-Length'));
    }

    public function testErrorDetailsAreLoggedNotShown(): void {
        $response = $this->request('GET', '/exception');

        self::assertSame(500, $response->code);
        self::assertSame('500 error', $response->body);
        self::assertStringContainsString('Uncaught exception: SELECT secret FROM internals', static::$server->getApplicationLog());
    }

    /**
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $path, string $body = '', array $headers = []): HttpResponse {
        return static::$client->request($method, $path, $body, $headers);
    }
}

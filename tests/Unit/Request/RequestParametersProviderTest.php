<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Request;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Request\Parameters\DataBuilder\FileParametersDataBuilder;
use Runway\Request\Parameters\Provider\RequestParametersProvider;

class RequestParametersProviderTest extends TestCase {
    /**
     * @var array<mixed>
     */
    protected array $server;

    protected function setUp(): void {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void {
        $_SERVER = $this->server;
        $_FILES = [];
    }

    public function testFilesOfField(): void {
        $_FILES = [
            'avatar' => ['name' => 'a.jpg', 'type' => 'image/jpeg', 'size' => 10, 'tmp_name' => '/tmp/a', 'error' => 0],
            'photos' => [
                'name'     => ['b.jpg', 'c.png'],
                'type'     => ['image/jpeg', 'image/png'],
                'size'     => [20, 30],
                'tmp_name' => ['/tmp/b', '/tmp/c'],
                'error'    => [0, 0],
            ],
        ];

        $files = $this->provider()->getFiles();

        self::assertSame(['avatar', 'photos[0]', 'photos[1]'], array_keys($files));
        self::assertSame('c.png', $files['photos[1]']->getName());
        self::assertSame('/tmp/c', $files['photos[1]']->getTmpName());
        self::assertSame(30, $files['photos[1]']->getSize());
    }

    #[DataProvider('ipAddressProvider')]
    public function testIpAddress(string $remoteAddress, ?string $forwardedFor, ?string $trustedProxies, string $expected): void {
        $_SERVER['REMOTE_ADDR'] = $remoteAddress;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $forwardedFor;

        self::assertSame($expected, $this->provider($trustedProxies)->getIpAddress());
    }

    /**
     * @return array<string, array{string, string|null, string|null, string}>
     */
    public static function ipAddressProvider(): array {
        return [
            'no proxies'                  => ['203.0.113.5', null, null, '203.0.113.5'],
            'forged header'               => ['203.0.113.5', '1.2.3.4', null, '203.0.113.5'],
            'header of an unknown server' => ['203.0.113.5', '1.2.3.4', '10.0.0.1', '203.0.113.5'],
            'trusted proxy'               => ['10.0.0.1', '198.51.100.7', '10.0.0.1', '198.51.100.7'],
            'forged start of the chain'   => ['10.0.0.1', '1.2.3.4, 198.51.100.7', '10.0.0.1', '198.51.100.7'],
            'chain of trusted proxies'    => ['10.0.0.2', '198.51.100.7, 10.0.0.1', '10.0.0.1, 10.0.0.2', '198.51.100.7'],
            'trusted proxy, no header'    => ['10.0.0.1', null, '10.0.0.1', '10.0.0.1'],
        ];
    }

    #[DataProvider('pathProvider')]
    public function testPath(string $requestUri, string $expected): void {
        $_SERVER['REQUEST_URI'] = $requestUri;

        self::assertSame($expected, $this->provider()->getPath());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function pathProvider(): array {
        return [
            'path with query' => ['/api/chats/42?number=1', '/api/chats/42'],
            'no path'         => ['?number=1', '/'],
            'malformed'       => ['//:80', '/'],
        ];
    }

    public function testServerParameters(): void {
        $_SERVER['REQUEST_METHOD'] = 'PATCH';
        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['SERVER_PORT'] = '8080';
        $_SERVER['REQUEST_URI'] = '/a?b=1';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['argv'] = ['console', 42, 'run'];

        $provider = $this->provider();

        self::assertSame('PATCH', $provider->getMethod());
        self::assertSame('example.com', $provider->getHost());
        self::assertSame(8080, $provider->getPort());
        self::assertSame('/a?b=1', $provider->getRequestUri());
        self::assertSame('https', $provider->getProtocol());
        self::assertSame(['console', 'run'], $provider->getArguments());
        self::assertSame($_SERVER, $provider->getServerParameters());
    }

    public function testMissingServerParameters(): void {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_HOST'], $_SERVER['SERVER_PORT'], $_SERVER['HTTPS']);
        $_SERVER['argv'] = 'not a list';
        $_SERVER['REQUEST_URI'] = ['not a string'];

        $provider = $this->provider();

        self::assertSame('', $provider->getMethod());
        self::assertSame('', $provider->getHost());
        self::assertSame(0, $provider->getPort());
        self::assertSame('', $provider->getRequestUri());
        self::assertSame('http', $provider->getProtocol());
        self::assertSame([], $provider->getArguments());
    }

    public function testHeadersOfCli(): void {
        $_SERVER['HTTP_X_CUSTOM_HEADER'] = 'value';
        $_SERVER['HTTP_X_NUMBER'] = 5;
        $_SERVER['HTTP_X_ARRAY'] = ['a'];
        $_SERVER['NOT_A_HEADER'] = 'value';

        $headers = $this->provider()->getHeaders();

        self::assertSame('value', $headers['x-custom-header']);
        self::assertSame('5', $headers['x-number']);
        self::assertArrayNotHasKey('x-array', $headers);
        self::assertArrayNotHasKey('not-a-header', $headers);
    }

    public function testCliRequest(): void {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.5';

        self::assertSame('127.0.0.1', new RequestParametersProvider(new FileParametersDataBuilder())->getIpAddress());
        self::assertSame('', new RequestParametersProvider(new FileParametersDataBuilder())->getBody());
    }

    public function testGlobals(): void {
        $get = $_GET;
        $post = $_POST;
        $cookies = $_COOKIE;
        $_GET = ['a' => '1'];
        $_POST = ['b' => '2'];
        $_COOKIE = ['c' => '3'];

        try {
            self::assertSame(['a' => '1'], $this->provider()->getGetParameters());
            self::assertSame(['b' => '2'], $this->provider()->getPostParameters());
            self::assertSame(['c' => '3'], $this->provider()->getCookies());
        } finally {
            $_GET = $get;
            $_POST = $post;
            $_COOKIE = $cookies;
        }
    }

    public function testNestedFiles(): void {
        $_FILES = [
            'docs' => [
                'name'     => ['a' => ['x.txt', 'y.txt'], 'b' => 'z.txt'],
                'type'     => ['a' => ['text/plain', 'text/plain'], 'b' => 'text/plain'],
                'size'     => ['a' => [1, 2], 'b' => 3],
                'tmp_name' => ['a' => ['/tmp/x', '/tmp/y'], 'b' => '/tmp/z'],
                'error'    => ['a' => [0, 0], 'b' => 0],
            ],
            'broken' => 'not a file',
        ];

        $files = $this->provider()->getFiles();

        self::assertSame(['docs[a][0]', 'docs[a][1]', 'docs[b]'], array_keys($files));
        self::assertSame('y.txt', $files['docs[a][1]']->getName());
        self::assertSame(2, $files['docs[a][1]']->getSize());
        self::assertSame('/tmp/z', $files['docs[b]']->getTmpName());
    }

    protected function provider(?string $trustedProxies = null): RequestParametersProvider {
        // The IP address of a CLI request is always 127.0.0.1.
        return new readonly class(new FileParametersDataBuilder(), $trustedProxies) extends RequestParametersProvider {
            protected function isCLI(): bool {
                return false;
            }
        };
    }
}

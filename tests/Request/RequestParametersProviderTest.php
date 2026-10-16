<?php

declare(strict_types=1);

namespace Runway\Tests\Request;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Request\Parameters\DataBuilder\FileParametersDataBuilder;
use Runway\Request\Parameters\Provider\RequestParametersProvider;

class RequestParametersProviderTest extends TestCase {
    protected array $server;

    protected function setUp(): void {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void {
        $_SERVER = $this->server;
    }

    #[DataProvider('ipAddressProvider')]
    public function testIpAddress(string $remoteAddress, ?string $forwardedFor, ?string $trustedProxies, string $expected): void {
        $_SERVER['REMOTE_ADDR'] = $remoteAddress;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = $forwardedFor;

        self::assertSame($expected, $this->provider($trustedProxies)->getIpAddress());
    }

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

    public static function pathProvider(): array {
        return [
            'path with query' => ['/api/chats/42?number=1', '/api/chats/42'],
            'no path'         => ['?number=1', '/'],
            'malformed'       => ['//:80', '/'],
        ];
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

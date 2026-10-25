<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Request;

use PHPUnit\Framework\TestCase;
use Runway\Exception\RuntimeException;
use Runway\Request\StreamedResponse;

class StreamedResponseTest extends TestCase {
    public function testBodyIsSentByPieces(): void {
        $response = new StreamedResponse(200, static function (callable $send): void {
            $send('first,');
            $send('second');
        });

        ob_start();
        $response->sendBody();

        self::assertSame('first,second', ob_get_clean());
        self::assertSame('', $response->getBody());
    }

    public function testNoContentLengthByDefault(): void {
        $response = new StreamedResponse(200, static function (callable $send): void {}, ['Content-Type' => 'text/csv']);

        self::assertSame(['Content-Type' => 'text/csv'], $response->getHeaders());
    }

    public function testGivenContentLengthIsKept(): void {
        $response = new StreamedResponse(200, static function (callable $send): void {}, ['Content-Length' => '12345']);

        self::assertSame('12345', $response->getHeaders()['Content-Length']);
    }

    public function testBodyIsSentOnce(): void {
        $response = new StreamedResponse(200, static function (callable $send): void {});
        $response->sendBody();

        $this->expectException(RuntimeException::class);
        $response->sendBody();
    }
}

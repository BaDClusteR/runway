<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Request;

use PHPUnit\Framework\TestCase;
use Runway\Request\Parameters\DataBuilder\FileParametersDataBuilder;
use Runway\Request\Parameters\DTO\CookieDTO;
use Runway\Request\Parameters\DTO\FileDTO;
use Runway\Request\Parameters\RequestParameterValue;
use Runway\Request\Response;
use Runway\Tests\Support\TemporaryDirectory;

class ParametersTest extends TestCase {
    use TemporaryDirectory;

    protected function tearDown(): void {
        $this->removeTemporaryDirectory();
    }

    public function testScalarValue(): void {
        $value = new RequestParameterValue('42');

        self::assertSame(42, $value->asInt());
        self::assertSame('42', $value->asString());
        self::assertTrue($value->asBool());
        self::assertSame(['42'], $value->asArray());
        self::assertFalse($value->isNull());
        self::assertSame('42', $value->asRaw());
    }

    public function testArrayValueIsNotScalar(): void {
        $value = new RequestParameterValue(['5', '6']);

        self::assertSame(0, $value->asInt());
        self::assertSame('', $value->asString());
        self::assertTrue($value->asBool());
        self::assertSame(['5', '6'], $value->asArray());
    }

    public function testNullValue(): void {
        $value = new RequestParameterValue(null);

        self::assertTrue($value->isNull());
        self::assertSame(0, $value->asInt());
        self::assertSame('', $value->asString());
        self::assertFalse($value->asBool());
        self::assertSame([], $value->asArray());
        self::assertFalse(new RequestParameterValue('0')->asBool());
    }

    public function testFileOfFilesArray(): void {
        $file = new FileParametersDataBuilder()->buildFileParameter([
            'name'      => 'a.txt',
            'type'      => 'text/plain',
            'size'      => '3',
            'tmp_name'  => '/tmp/a',
            'error'     => 0,
            'full_path' => 'dir/a.txt',
        ]);

        self::assertSame('a.txt', $file->getName());
        self::assertSame('text/plain', $file->getType());
        self::assertSame(3, $file->getSize());
        self::assertSame('/tmp/a', $file->getTmpName());
        self::assertSame('0', $file->getError());
        self::assertSame('dir/a.txt', $file->getFullPath());
    }

    public function testFileWithoutData(): void {
        $file = new FileParametersDataBuilder()->buildFileParameter(['name' => ['a'], 'size' => 'big']);

        self::assertSame('', $file->getName());
        self::assertSame('', $file->getType());
        self::assertSame(0, $file->getSize());
        self::assertSame('', $file->getTmpName());
        self::assertSame('', $file->getError());
        self::assertSame('', $file->getFullPath());
    }

    public function testFileContent(): void {
        $this->createTemporaryDirectory();
        $path = $this->createFile('upload', 'content');

        self::assertSame('content', new FileDTO('a', '', 7, $path, '0', '')->getContent());
    }

    public function testCookie(): void {
        $cookie = new CookieDTO('session', 'abc', 100, '/app', 'example.com', true, true, 'Strict');

        self::assertSame('session', $cookie->getName());
        self::assertSame('abc', $cookie->getValue());
        self::assertSame(100, $cookie->getExpires());
        self::assertSame('/app', $cookie->getPath());
        self::assertSame('example.com', $cookie->getDomain());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttponly());
        self::assertSame('Strict', $cookie->getSameSite());
        self::assertSame('Lax', new CookieDTO('a', 'b', 0, '/', '', false, false)->getSameSite());
    }

    public function testResponse(): void {
        $cookie = new CookieDTO('a', 'b', 0, '/', '', false, false);
        $response = new Response(200, 'body', ['X-A' => '1']);

        self::assertSame(200, $response->getCode());
        self::assertSame('body', $response->getBody());
        self::assertSame(['X-A' => '1', 'Content-Length' => '4'], $response->getHeaders());
        self::assertSame([], $response->getCookies());

        $response->setCode(404)
                 ->setBody('not found')
                 ->addHeader('X-B', '2')
                 ->addHeaders(['X-A' => 'new', 'X-C' => '3'])
                 ->addCookie($cookie);

        self::assertSame(404, $response->getCode());
        self::assertSame(['X-A' => 'new', 'X-B' => '2', 'X-C' => '3', 'Content-Length' => '9'], $response->getHeaders());
        self::assertSame([$cookie], $response->getCookies());

        $response->setHeaders(['Content-Length' => '100'])->setCookies([]);
        self::assertSame(['Content-Length' => '100'], $response->getHeaders());
        self::assertSame([], $response->getCookies());
        self::assertSame(0, new Response()->getCode());
        self::assertSame(['Content-Length' => '0'], new Response()->getHeaders());
    }
}

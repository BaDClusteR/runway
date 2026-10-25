<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Router;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Router\Compiler\RouteCompiler;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\Exception\Route\RouteException;

class RouteCompilerTest extends TestCase {
    public function testStaticPath(): void {
        $compiled = new RouteCompiler()->compile($this->route('/chats/search'));

        self::assertSame('/chats/search', $compiled->staticPath);
        self::assertSame([], $compiled->variables);
        self::assertSame([RouteCompiler::SPECIFICITY_STATIC, RouteCompiler::SPECIFICITY_STATIC], $compiled->specificity);
    }

    public function testLeadingSlashIsOptional(): void {
        self::assertSame('/ping', new RouteCompiler()->compile($this->route('ping'))->staticPath);
        self::assertSame('/', new RouteCompiler()->compile($this->route('/'))->staticPath);
    }

    public function testPlaceholders(): void {
        $compiled = new RouteCompiler()->compile($this->route('/chats/{chatId}/chunks/{index}'));

        self::assertNull($compiled->staticPath);
        self::assertSame(['chatId', 'index'], $compiled->variables);
        self::assertSame(1, preg_match($compiled->regexp, '/chats/42/chunks/3', $matches));
        self::assertSame('42', $matches['chatId']);
        self::assertSame('3', $matches['index']);
        self::assertSame(0, preg_match($compiled->regexp, '/chats/42/chunks/3/more'));
        self::assertSame(0, preg_match($compiled->regexp, '/chats/42/chunks/'));
    }

    public function testRequirements(): void {
        $compiled = new RouteCompiler()->compile($this->route('/chats/{chatId}', requirements: ['chatId' => '\d+']));

        self::assertSame(1, preg_match($compiled->regexp, '/chats/42'));
        self::assertSame(0, preg_match($compiled->regexp, '/chats/abc'));
    }

    public function testInlineRequirementWinsAndCanContainBraces(): void {
        $compiled = new RouteCompiler()->compile(
            $this->route('/archive/{year<\d{4}>}', requirements: ['year' => '[a-z]+'])
        );

        self::assertSame(['year'], $compiled->variables);
        self::assertSame(1, preg_match($compiled->regexp, '/archive/2026'));
        self::assertSame(0, preg_match($compiled->regexp, '/archive/abc'));
        self::assertSame(0, preg_match($compiled->regexp, '/archive/20261'));
    }

    public function testDelimiterInRequirement(): void {
        $compiled = new RouteCompiler()->compile($this->route('/tags/{tag<#\w+>}'));

        self::assertSame(1, preg_match($compiled->regexp, '/tags/#php'));
    }

    public function testLiteralsAreEscaped(): void {
        $compiled = new RouteCompiler()->compile($this->route('/files/{name}.{extension}'));

        self::assertSame(1, preg_match($compiled->regexp, '/files/report.pdf', $matches));
        self::assertSame('pdf', $matches['extension']);
        self::assertSame(0, preg_match($compiled->regexp, '/files/reportXpdf'));
    }

    public function testUnicodePath(): void {
        $compiled = new RouteCompiler()->compile($this->route('/документы/{name}'));

        self::assertSame(1, preg_match($compiled->regexp, '/документы/Договор аренды.rtf', $matches));
        self::assertSame('Договор аренды.rtf', $matches['name']);
    }

    /**
     * @param int[] $expected
     */
    #[DataProvider('specificityProvider')]
    public function testSpecificity(string $path, array $expected): void {
        self::assertSame($expected, new RouteCompiler()->compile($this->route($path))->specificity);
    }

    /**
     * @return array<string, array{string, int[]}>
     */
    public static function specificityProvider(): array {
        return [
            'static'      => ['/contacts/search', [3, 3]],
            'placeholder' => ['/contacts/{id}', [3, 1]],
            'mixed'       => ['/files/{name}.{extension}', [3, 2]],
            'catch-all'   => ['/docs/{path<.+>}', [3, 0]],
        ];
    }

    /**
     * @param array<string, string> $requirements
     */
    #[DataProvider('invalidPathProvider')]
    public function testInvalidPath(string $path, array $requirements = []): void {
        $this->expectException(RouteException::class);

        new RouteCompiler()->compile($this->route($path, requirements: $requirements));
    }

    /**
     * @return array<string, array{0: string, 1?: array<string, string>}>
     */
    public static function invalidPathProvider(): array {
        return [
            'not closed'          => ['/chats/{chatId'],
            'requirement not closed' => ['/chats/{chatId<\d+}'],
            'invalid name'        => ['/chats/{chat-id}'],
            'empty name'          => ['/chats/{}'],
            'duplicate'           => ['/chats/{id}/messages/{id}'],
            'invalid requirement' => ['/chats/{id<(\d+>}'],
            'invalid requirement from the route' => ['/chats/{id}', ['id' => '[']],
        ];
    }

    /**
     * @param array<string, string> $requirements
     */
    protected function route(string $path, array $requirements = []): RouteDTO {
        return new RouteDTO('test', $path, 'Controller', 'run', 0, '', 'routes.yaml', [], $requirements);
    }
}

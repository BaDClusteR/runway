<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use Runway\Router\Compiler\RouteCompiler;
use Runway\Router\Matcher\RouteMatcher;
use Runway\Router\Provider\IRouteProvider;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\Exception\Route\RouteDeclaredMoreThanOnce;
use Runway\Service\Exception\Route\RouteException;

class RouteMatcherTest extends TestCase {
    public function testMatchesByPathAndMethod(): void {
        $matcher = $this->matcher([
            $this->route('chat', '/chats/{chatId<\d+>}', ['GET']),
            $this->route('chunk', '/chats/{chatId<\d+>}/chunks/{index<\d+>}', ['GET']),
        ]);

        $result = $matcher->match('/chats/42/chunks/3', 'GET');

        self::assertSame('chunk', $result->match?->route->name);
        self::assertSame(['chatId' => '42', 'index' => '3'], $result->match->parameters);
        self::assertSame('chat', $matcher->match('/chats/42', 'get')->match?->route->name);
    }

    public function testNotFound(): void {
        $result = $this->matcher([$this->route('chat', '/chats/{chatId<\d+>}')])->match('/chats/abc', 'GET');

        self::assertNull($result->match);
        self::assertSame([], $result->allowedMethods);
    }

    public function testMethodNotAllowed(): void {
        $matcher = $this->matcher([
            $this->route('get', '/chats/{chatId}', ['GET']),
            $this->route('update', '/chats/{chatId}', ['PATCH', 'DELETE']),
        ]);

        $result = $matcher->match('/chats/42', 'POST');

        self::assertNull($result->match);
        self::assertSame(['GET', 'HEAD', 'PATCH', 'DELETE'], $result->allowedMethods);
        self::assertSame('update', $matcher->match('/chats/42', 'DELETE')->match?->route->name);
    }

    public function testHeadMatchesGet(): void {
        $result = $this->matcher([$this->route('ping', '/ping', ['GET'])])->match('/ping', 'HEAD');

        self::assertSame('ping', $result->match?->route->name);
    }

    public function testRouteWithoutMethodsMatchesAnyMethod(): void {
        $result = $this->matcher([$this->route('any', '/any')])->match('/any', 'PUT');

        self::assertSame('any', $result->match?->route->name);
    }

    public function testStaticSegmentWinsRegardlessOfOrder(): void {
        $matcher = $this->matcher([
            $this->route('catch-all', '/contacts/{path<.+>}'),
            $this->route('contact', '/contacts/{contactId}'),
            $this->route('search', '/contacts/search'),
        ]);

        self::assertSame('search', $matcher->match('/contacts/search', 'GET')->match?->route->name);
        self::assertSame('contact', $matcher->match('/contacts/42', 'GET')->match?->route->name);
        self::assertSame('catch-all', $matcher->match('/contacts/42/photos', 'GET')->match?->route->name);
    }

    public function testEarlierSegmentsMatterMore(): void {
        $matcher = $this->matcher([
            $this->route('param-first', '/{section}/latest'),
            $this->route('static-first', '/chats/{chatId}'),
        ]);

        self::assertSame('static-first', $matcher->match('/chats/latest', 'GET')->match?->route->name);
    }

    public function testPriorityWinsOverSpecificity(): void {
        $matcher = $this->matcher([
            $this->route('search', '/contacts/search'),
            $this->route('contact', '/contacts/{contactId}', priority: 10),
        ]);

        self::assertSame('contact', $matcher->match('/contacts/search', 'GET')->match?->route->name);
    }

    public function testSameSpecificityKeepsDeclarationOrder(): void {
        $matcher = $this->matcher([
            $this->route('digits', '/items/{id<\d+>}'),
            $this->route('any', '/items/{slug}'),
        ]);

        self::assertSame('digits', $matcher->match('/items/42', 'GET')->match?->route->name);
        self::assertSame('any', $matcher->match('/items/abc', 'GET')->match?->route->name);
    }

    public function testMatchingMethodBeatsMoreSpecificPath(): void {
        $matcher = $this->matcher([
            $this->route('chat', '/chats/{chatId}', ['GET']),
            $this->route('fallback', '/chats/{path<.+>}', ['POST']),
        ]);

        self::assertSame('fallback', $matcher->match('/chats/42', 'POST')->match?->route->name);
    }

    public function testRoutesOfAllProvidersAreUsed(): void {
        $matcher = new RouteMatcher(new RouteCompiler(), [
            $this->provider([$this->route('a', '/a')]),
            $this->provider([$this->route('b', '/b')]),
        ]);

        self::assertSame('b', $matcher->match('/b', 'GET')->match?->route->name);
    }

    public function testConflictingRoutes(): void {
        $this->expectException(RouteException::class);
        $this->expectExceptionMessageMatches('/"second".*conflicts with route "first"/');

        $this->matcher([
            $this->route('first', '/chats/{chatId}', ['GET', 'POST']),
            $this->route('second', '/chats/{id}', ['POST']),
        ])->match('/chats/42', 'GET');
    }

    public function testSamePathWithOtherMethodsIsNotConflict(): void {
        $matcher = $this->matcher([
            $this->route('get', '/chats/{chatId}', ['GET']),
            $this->route('delete', '/chats/{chatId}', ['DELETE']),
        ]);

        self::assertSame('delete', $matcher->match('/chats/42', 'DELETE')->match?->route->name);
    }

    public function testDuplicateNames(): void {
        $this->expectException(RouteDeclaredMoreThanOnce::class);

        new RouteMatcher(new RouteCompiler(), [
            $this->provider([$this->route('same', '/a')]),
            $this->provider([$this->route('same', '/b')]),
        ])->match('/a', 'GET');
    }

    /**
     * @param RouteDTO[] $routes
     */
    protected function matcher(array $routes): RouteMatcher {
        return new RouteMatcher(new RouteCompiler(), [$this->provider($routes)]);
    }

    /**
     * @param RouteDTO[] $routes
     */
    protected function provider(array $routes): IRouteProvider {
        return new readonly class($routes) implements IRouteProvider {
            /**
             * @param RouteDTO[] $routes
             */
            public function __construct(private array $routes) {}

            public function getRoutes(): array {
                return $this->routes;
            }
        };
    }

    /**
     * @param string[] $methods
     */
    protected function route(string $name, string $path, array $methods = [], int $priority = 0): RouteDTO {
        return new RouteDTO($name, $path, 'Controller', 'run', $priority, '', 'routes.yaml', $methods);
    }
}

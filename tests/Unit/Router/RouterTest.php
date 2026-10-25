<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Router;

use PHPUnit\Framework\TestCase;
use Runway\Exception\RuntimeException;
use Runway\Request\IRequest;
use Runway\Router\DTO\RouteMatchDTO;
use Runway\Router\DTO\RouteMatchResultDTO;
use Runway\Router\Matcher\IRouteMatcher;
use Runway\Router\Provider\ConfigRouteProvider;
use Runway\Router\Router;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\Exception\Route\RouteControllerNotFound;
use Runway\Service\Exception\Route\RouteMethodNotFound;
use Runway\Tests\Fixture\Controller\FixtureController;
use Runway\Tests\Fixture\Controller\PlainController;
use Runway\Tests\Support\ContainerState;

/**
 * The controllers of tests/Fixture/Controller: FixtureController is a service, PlainController is not.
 */
class RouterTest extends TestCase {
    protected function setUp(): void {
        ContainerState::reset();
    }

    public function testControllerGetsPlaceholders(): void {
        $route = $this->route(FixtureController::class, 'item');
        $request = $this->createMock(IRequest::class);
        $request->expects($this->once())->method('setRouteMatch')->with(new RouteMatchDTO($route, ['id' => '42']));

        $matcher = $this->createMock(IRouteMatcher::class);
        $matcher->expects($this->once())
                ->method('match')
                // The path is decoded.
                ->with('/items/4 2', 'DELETE')
                ->willReturn(new RouteMatchResultDTO(new RouteMatchDTO($route, ['id' => '42'])));

        $response = new Router($matcher, $request)->process('/items/4%202', 'DELETE');

        // The controller service has the request of the container (the CLI one).
        self::assertSame('{"id":"42","method":""}', $response->getBody());
    }

    public function testControllerThatIsNotService(): void {
        self::assertSame('plain', $this->process($this->route(PlainController::class, 'index'))->getBody());
    }

    public function testMissingController(): void {
        $this->expectException(RouteControllerNotFound::class);

        $this->process($this->route('App\Missing', 'index'));
    }

    public function testMissingMethod(): void {
        $this->expectException(RouteMethodNotFound::class);

        $this->process($this->route(FixtureController::class, 'missing'));
    }

    public function testProtectedMethod(): void {
        $this->expectException(RouteMethodNotFound::class);

        $this->process($this->route(FixtureController::class, 'secret'));
    }

    public function testControllerMustReturnResponse(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Controller ' . FixtureController::class . '::notResponse of route "test" returned string instead of Runway\Request\Response.'
        );

        $this->process($this->route(FixtureController::class, 'notResponse'));
    }

    public function testNotFound(): void {
        $response = $this->process(null);

        self::assertSame(404, $response->getCode());
    }

    public function testMethodNotAllowed(): void {
        $response = $this->process(null, ['GET']);

        self::assertSame(405, $response->getCode());
        self::assertSame('GET, OPTIONS', $response->getHeaders()['Allow']);
    }

    public function testRoutesOfConfig(): void {
        $names = array_map(static fn(RouteDTO $route): string => $route->name, new ConfigRouteProvider()->getRoutes());

        // tests/config/routes.yaml.
        self::assertContains('fixture_hello', $names);
        self::assertContains('fixture_files', $names);
    }

    /**
     * @param string[] $allowedMethods
     */
    protected function process(?RouteDTO $route, array $allowedMethods = []): \Runway\Request\Response {
        $matcher = $this->createStub(IRouteMatcher::class);
        $matcher->method('match')->willReturn(
            new RouteMatchResultDTO($route ? new RouteMatchDTO($route, []) : null, $allowedMethods)
        );

        return new Router($matcher, $this->createStub(IRequest::class))->process('/path');
    }

    protected function route(string $controller, string $method): RouteDTO {
        return new RouteDTO('test', '/path', $controller, $method, 0, '', '/routes.yaml');
    }
}

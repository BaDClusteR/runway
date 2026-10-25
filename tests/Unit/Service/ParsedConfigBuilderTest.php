<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\Event\Exception\EmptyEventMethod;
use Runway\Event\Exception\EmptyEventName;
use Runway\Event\Exception\EventMethodDoesNotExist;
use Runway\Service\DataBuilder\ParsedConfigBuilder;
use Runway\Service\Exception\ConfigurationException;
use Runway\Service\Exception\Route\RouteException;
use Runway\Tests\Fixture\Service\EventListener;
use Runway\Tests\Fixture\Service\Greeter;

class ParsedConfigBuilderTest extends TestCase {
    public function testService(): void {
        $services = $this->builder([
            'services' => [
                'greeter' => [
                    'class'     => Greeter::class,
                    'decorates' => 'other',
                    'arguments' => ['name' => 'value', 'service' => '@other'],
                    'tags'      => [['name' => 'tag', 'priority' => 5], 'plain.tag'],
                ],
            ],
        ])->buildParsedServices();

        self::assertCount(1, $services);
        $service = $services[0];
        self::assertSame('greeter', $service->getName());
        self::assertSame(Greeter::class, $service->getClass());
        self::assertSame('other', $service->getDecorates());
        self::assertSame('/config/services.yaml', $service->getFilePath());
        self::assertSame(
            [['name', 'value'], ['service', '@other']],
            array_map(static fn($argument): array => [$argument->getName(), $argument->getValue()], $service->getArguments())
        );
        self::assertSame(
            [['tag', ['priority' => 5]], ['plain.tag', []]],
            array_map(static fn($tag): array => [$tag->getName(), $tag->getExtra()], $service->getTags())
        );
        self::assertSame([], $service->getEvents());
    }

    public function testServiceWithoutOptionsIsItsClass(): void {
        $services = $this->builder(['services' => [Greeter::class => null, 'other' => ['class' => '']]])->buildParsedServices();

        self::assertSame(Greeter::class, $services[0]->getClass());
        self::assertSame('', $services[0]->getDecorates());
        self::assertSame([], $services[0]->getArguments());
        self::assertSame([], $services[0]->getTags());
        self::assertSame('other', $services[1]->getClass());
    }

    public function testEvents(): void {
        $services = $this->builder([
            'services' => [
                'listener' => [
                    'class'  => EventListener::class,
                    'events' => [['event' => 'fixture.event', 'method' => 'onFixtureEvent']],
                ],
            ],
        ])->buildParsedServices();

        $event = $services[0]->getEvents()[0];
        self::assertSame('fixture.event', $event->getName());
        self::assertSame('listener', $event->getServiceName());
        self::assertSame('onFixtureEvent', $event->getMethod());
    }

    /**
     * @return iterable<string, array{array<mixed>, class-string<\Throwable>, string}>
     */
    public static function invalidEventProvider(): iterable {
        yield 'no name' => [
            ['method' => 'onFixtureEvent'],
            EmptyEventName::class,
            'Configuration error in /config/services.yaml: Event name is not set in service listener.',
        ];
        yield 'not a map' => [
            [],
            EmptyEventName::class,
            'Event name is not set in service listener.',
        ];
        yield 'no method' => [
            ['event' => 'fixture.event'],
            EmptyEventMethod::class,
            'Method name is not set in event fixture.event (service listener).',
        ];
        yield 'unknown method' => [
            ['event' => 'fixture.event', 'method' => 'unknown'],
            EventMethodDoesNotExist::class,
            'Service listener does not have public method unknown (called in event fixture.event).',
        ];
    }

    /**
     * @param array<mixed>             $event
     * @param class-string<\Throwable> $exception
     */
    #[DataProvider('invalidEventProvider')]
    public function testInvalidEvent(array $event, string $exception, string $message): void {
        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        $this->builder([
            'services' => ['listener' => ['class' => EventListener::class, 'events' => [$event ?: 'not a map']]],
        ])->buildParsedServices();
    }

    public function testEventExceptionHasServiceAndEvent(): void {
        try {
            $this->builder([
                'services' => ['listener' => ['class' => EventListener::class, 'events' => [['event' => 'e']]]],
            ])->buildParsedServices();
            self::fail('No exception');
        } catch (EmptyEventMethod $e) {
            self::assertSame('listener', $e->getService()->getName());
            self::assertSame(EventListener::class, $e->getService()->getClass());
            self::assertSame('/config/services.yaml', $e->getService()->getFilePath());
            self::assertSame('e', $e->getEvent()->getName());
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidTagProvider(): iterable {
        yield 'no name' => [['priority' => 1]];
        yield 'empty name' => [['name' => '']];
        yield 'not a string' => [42];
        yield 'empty string' => [''];
    }

    #[DataProvider('invalidTagProvider')]
    public function testTagWithoutName(mixed $tag): void {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Configuration error in /config/services.yaml: A tag of service greeter has no name.');

        $this->builder(['services' => ['greeter' => ['tags' => [$tag]]]])->buildParsedServices();
    }

    public function testRoute(): void {
        $routes = $this->builder([
            'routes' => [
                'chat' => [
                    'path'         => '/chats/{id}',
                    'controller'   => 'ChatController::get',
                    'methods'      => ['get', 'POST', 'GET'],
                    'requirements' => ['id' => 42],
                    'priority'     => '3',
                    'description'  => 'A chat',
                ],
            ],
        ])->buildRoutes();

        self::assertCount(1, $routes);
        $route = $routes[0];
        self::assertSame('chat', $route->name);
        self::assertSame('/chats/{id}', $route->path);
        self::assertSame('ChatController', $route->controller);
        self::assertSame('get', $route->method);
        self::assertSame(['GET', 'POST'], $route->methods);
        self::assertSame(['id' => '42'], $route->requirements);
        self::assertSame(3, $route->priority);
        self::assertSame('A chat', $route->description);
        self::assertSame('/config/services.yaml', $route->filePath);
    }

    public function testRouteDefaults(): void {
        $route = $this->builder([
            'routes' => [7 => ['path' => '/', 'controller' => 'C::m', 'methods' => 'patch', 'priority' => 'high']],
        ])->buildRoutes()[0];

        self::assertSame('7', $route->name);
        self::assertSame(['PATCH'], $route->methods);
        self::assertSame([], $route->requirements);
        self::assertSame(0, $route->priority);
        self::assertSame('', $route->description);
    }

    public function testRouteWithoutOptionalMethods(): void {
        $route = $this->builder(['routes' => ['r' => ['path' => '/', 'controller' => 'C::m']]])->buildRoutes()[0];

        self::assertSame([], $route->methods);
        self::assertSame(0, $route->priority);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidRouteProvider(): iterable {
        yield 'not a map' => ['/path', 'Route "r" is not a map of its options'];
        yield 'no path' => [['controller' => 'C::m'], 'Empty route path'];
        yield 'no controller' => [['path' => '/'], 'Empty route controller'];
        yield 'no class' => [['path' => '/', 'controller' => '::m'], 'Empty route controller'];
        yield 'no method' => [['path' => '/', 'controller' => 'C::'], 'Empty route method'];
        yield 'no separator' => [['path' => '/', 'controller' => 'C'], 'Empty route method'];
        yield 'controller is not a string' => [['path' => '/', 'controller' => ['C', 'm']], 'Empty route controller'];
    }

    #[DataProvider('invalidRouteProvider')]
    public function testInvalidRoute(mixed $route, string $message): void {
        try {
            $this->builder(['routes' => ['r' => $route]])->buildRoutes();
            self::fail('No exception');
        } catch (RouteException $e) {
            self::assertSame("Configuration error in /config/services.yaml: $message", $e->getMessage());
            self::assertSame('/config/services.yaml', $e->route->filePath);
            self::assertSame('/config/services.yaml', $e->filePath);
        }
    }

    public function testParameters(): void {
        $parameters = $this->builder(['parameters' => ['name' => 'value', 'list' => [1, 2]]])->buildParameters();

        self::assertSame(
            [['name', 'value'], ['list', [1, 2]]],
            array_map(static fn($parameter): array => [$parameter->name, $parameter->value], $parameters)
        );
    }

    public function testEmptyConfig(): void {
        $builder = $this->builder([]);

        self::assertSame([], $builder->buildParsedServices());
        self::assertSame([], $builder->buildRoutes());
        self::assertSame([], $builder->buildParameters());
    }

    /**
     * @param array<mixed> $rawData
     */
    protected function builder(array $rawData): ParsedConfigBuilder {
        return new ParsedConfigBuilder()->setRawData($rawData)->setFilePath('/config/services.yaml');
    }
}

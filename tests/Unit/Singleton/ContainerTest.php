<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Singleton;

use PHPUnit\Framework\TestCase;
use Runway\DataStorage\QueryBuilder\IQueryBuilder;
use Runway\Event\EventDispatcher;
use Runway\Event\IEventDispatcher;
use Runway\Exception\CircularDependencyException;
use Runway\Service\Exception\ClassNotFoundException;
use Runway\Service\Exception\ServiceDecoratorCallException;
use Runway\Service\Exception\ServiceNotFoundException;
use Runway\Service\Exception\ServiceUntypedParameterInConstructor;
use Runway\Singleton\Container;
use Runway\Tests\Fixture\Service\ArgumentsService;
use Runway\Tests\Fixture\Service\CircularA;
use Runway\Tests\Fixture\Service\CircularB;
use Runway\Tests\Fixture\Service\CircularEntry;
use Runway\Tests\Fixture\Service\ExclamationGreeter;
use Runway\Tests\Fixture\Service\Greeter;
use Runway\Tests\Fixture\Service\IGreeter;
use Runway\Tests\Fixture\Service\MissingDependencyService;
use Runway\Tests\Fixture\Service\PoliteGreeter;
use Runway\Tests\Fixture\Service\SingletonService;
use Runway\Tests\Fixture\Service\TaggedService;
use Runway\Tests\Fixture\Service\UntypedService;
use Runway\Tests\Support\ContainerState;
use Runway\Tests\Support\FakeDataStorageDriver;

/**
 * The container with the fixture services of tests/config/services.yaml.
 */
class ContainerTest extends TestCase {
    protected Container $container;

    protected function setUp(): void {
        $this->container = ContainerState::reset();
    }

    public function testServiceOfInterface(): void {
        self::assertInstanceOf(EventDispatcher::class, $this->container->getService(IEventDispatcher::class));
        self::assertTrue($this->container->hasService(IEventDispatcher::class));
        self::assertFalse($this->container->hasService('fixture.unknown'));
    }

    public function testEveryCallCreatesNewInstance(): void {
        self::assertNotSame(
            $this->container->getService(IGreeter::class),
            $this->container->getService(IGreeter::class)
        );
    }

    public function testDecoratorsWrapServiceInDeclarationOrder(): void {
        $greeter = $this->container->getService(IGreeter::class);

        self::assertInstanceOf(PoliteGreeter::class, $greeter);
        self::assertInstanceOf(ExclamationGreeter::class, $greeter->inner);
        self::assertInstanceOf(Greeter::class, $greeter->inner->inner);
        self::assertSame('Please, Hello, Bob!', $greeter->greet('Bob'));
    }

    public function testDecoratorCannotBeRequestedDirectly(): void {
        $this->expectException(ServiceDecoratorCallException::class);
        $this->expectExceptionMessage(
            'Cannot call decorator service ' . ExclamationGreeter::class . ', call ' . IGreeter::class . ' instead.'
        );

        $this->container->getService(ExclamationGreeter::class);
    }

    public function testArguments(): void {
        $service = $this->container->getService(ArgumentsService::class);

        self::assertSame('literal', $service->name);
        self::assertSame('parameter value', $service->parameter);
        // test.env: LOG_LEVEL=4 (a typed value).
        self::assertSame(4, $service->logLevel);
        self::assertInstanceOf(PoliteGreeter::class, $service->greeter);
        self::assertInstanceOf(EventDispatcher::class, $service->autowired);
        // A nullable parameter is null rather than a service.
        self::assertNull($service->nullable);
        self::assertSame(5, $service->default);
    }

    public function testCircularDependency(): void {
        try {
            $this->container->getService(CircularA::class);
            self::fail('No exception');
        } catch (CircularDependencyException $e) {
            $chain = array_map(static fn($link): string => $link->serviceName, $e->getDependencyChain());

            self::assertSame([CircularA::class, CircularB::class], $chain);
            self::assertStringContainsString("Circular dependency detected:\n1: " . CircularA::class, $e->getMessage());
            self::assertStringContainsString("\n2: " . CircularB::class . ' in ', $e->getMessage());
        }

        // The chain is clean after the exception: the next request is the same error, not another one.
        $this->expectException(CircularDependencyException::class);
        $this->container->getService(CircularB::class);
    }

    public function testCircularDependencyChainStartsAtCycle(): void {
        try {
            $line = __LINE__ + 1;
            $this->container->getService(CircularEntry::class);
            self::fail('No exception');
        } catch (CircularDependencyException $e) {
            $chain = $e->getDependencyChain();

            self::assertSame([CircularA::class, CircularB::class], array_map(static fn($link): string => $link->serviceName, $chain));

            // The call outside of the container.
            foreach ($chain as $link) {
                self::assertSame(__FILE__, $link->fileName);
                self::assertSame($line, $link->line);
                self::assertSame(Container::class . '::getService', $link->method);
            }
        }
    }

    public function testServicesByTagPriorityDefaults(): void {
        self::assertSame(
            ['minus', 'none', 'text', 'one'],
            array_map(
                static fn(mixed $service): string => $service instanceof TaggedService ? $service->name : '',
                $this->container->getServicesByTag('fixture.priority')
            )
        );
    }

    public function testDependencyChainIsCleanAfterMissingService(): void {
        self::assertNull($this->container->tryGetService('fixture.unknown'));
        self::assertNull($this->container->tryGetService('fixture.unknown'));

        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('Service fixture.unknown not found.');
        $this->container->getService('fixture.unknown');
    }

    public function testTryGetServiceGivesService(): void {
        self::assertInstanceOf(PoliteGreeter::class, $this->container->tryGetService(IGreeter::class));
    }

    public function testMissingClass(): void {
        $this->expectException(ClassNotFoundException::class);
        $this->expectExceptionMessage(
            'class Runway\Tests\Fixture\Service\DoesNotExist not found (required in service fixture.missing_class)'
        );

        $this->container->getService('fixture.missing_class');
    }

    public function testUntypedConstructorParameter(): void {
        try {
            $this->container->getService(UntypedService::class);
            self::fail('No exception');
        } catch (ServiceUntypedParameterInConstructor $e) {
            self::assertSame('value', $e->getParameter());
            self::assertStringContainsString('Parameter value in the service\'s ' . UntypedService::class, $e->getMessage());
        }
    }

    public function testMissingDependency(): void {
        $this->expectException(ServiceNotFoundException::class);
        $this->expectExceptionMessage('Service Countable not found.');

        $this->container->getService(MissingDependencyService::class);
    }

    public function testSingletonServiceIsTheInstance(): void {
        $service = $this->container->getService(SingletonService::class);

        self::assertSame(SingletonService::getInstance(), $service);
        self::assertSame($service, $this->container->getService(SingletonService::class));
        self::assertInstanceOf(PoliteGreeter::class, $service->greeter);
        self::assertSame('default', $service->label);
    }

    public function testServicesByTagAreSortedByPriority(): void {
        $services = $this->container->getServicesByTag('fixture.tagged');

        self::assertSame(
            ['first', 'second'],
            array_map(static fn(mixed $service): string => $service instanceof TaggedService ? $service->name : '', $services)
        );
        self::assertSame([], $this->container->getServicesByTag('fixture.unknown_tag'));
    }

    public function testServicesByTagWithExtraFilters(): void {
        $services = $this->container->getServicesByTag('fixture.tagged', ['type' => 'json']);

        self::assertCount(1, $services);
        self::assertInstanceOf(TaggedService::class, $services[0]);
        self::assertSame('second', $services[0]->name);
        self::assertSame([], $this->container->getServicesByTag('fixture.tagged', ['type' => 'yaml']));
        self::assertSame([], $this->container->getServicesByTag('fixture.tagged', ['missing' => 'json']));
        self::assertCount(2, $this->container->getServicesByTag('fixture.tagged', []));
    }

    public function testServiceWithSeveralTags(): void {
        $services = $this->container->getServicesByTag('fixture.other_tag');

        self::assertCount(1, $services);
        self::assertInstanceOf(TaggedService::class, $services[0]);
        self::assertSame('first', $services[0]->name);
    }

    public function testServiceTagsByName(): void {
        self::assertSame(
            [
                ['serviceName' => 'fixture.tagged.second', 'extra' => ['priority' => 20, 'type' => 'json']],
                ['serviceName' => 'fixture.tagged.first', 'extra' => ['priority' => 10, 'type' => 'xml']],
            ],
            $this->container->getServiceTagsByName('fixture.tagged')
        );
        self::assertSame([], $this->container->getServiceTagsByName('fixture.unknown_tag'));
    }

    public function testEventDispatcher(): void {
        self::assertInstanceOf(EventDispatcher::class, $this->container->getEventDispatcher());
    }

    public function testDataStorageDriverIsConnectedOnce(): void {
        $driver = new FakeDataStorageDriver();
        $container = ContainerState::withDataStorageDriver($driver);

        self::assertSame($driver, $container->getDataStorageDriver());
        self::assertNull($driver->connectedWith);
    }

    public function testQueryBuilderIsSharedAndUsesDriver(): void {
        $driver = new FakeDataStorageDriver();
        $container = ContainerState::withDataStorageDriver($driver);

        $queryBuilder = $container->getQueryBuilder();
        $queryBuilder->select('1')->getFirstScalarResult();

        self::assertInstanceOf(IQueryBuilder::class, $queryBuilder);
        self::assertSame($queryBuilder, $container->getQueryBuilder());
        self::assertSame(['SELECT 1'], $driver->getQueries());
    }
}

<?php

declare(strict_types=1);

namespace Runway\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Runway\Exception\RuntimeException;
use Runway\Singleton;
use Runway\Tests\Fixture\Service\IGreeter;
use Runway\Tests\Fixture\Service\SingletonService;

class SingletonTest extends TestCase {
    public function testInstanceIsShared(): void {
        self::assertSame(SingletonService::getInstance(), SingletonService::getInstance());
    }

    public function testConstructorGetsServicesAndDefaults(): void {
        $instance = SingletonService::resetInstance();

        self::assertInstanceOf(IGreeter::class, $instance->greeter);
        self::assertSame('Please, Hello, Bob!', $instance->greeter->greet('Bob'));
        self::assertSame('default', $instance->label);
    }

    public function testResetGivesNewInstance(): void {
        $instance = SingletonService::getInstance();

        self::assertNotSame($instance, SingletonService::resetInstance());
        self::assertSame(SingletonService::getInstance(), SingletonService::getInstance());
    }

    public function testEveryClassHasItsInstance(): void {
        self::assertNotSame(Singleton::getInstance(), SingletonService::getInstance());
        self::assertSame(Singleton::class, get_class(Singleton::getInstance()));
    }

    public function testParameterWithoutServiceOrDefault(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Cannot instantiate ' . NotServiceSingleton::class . ': parameter $countable should be either a service or have default value.'
        );

        NotServiceSingleton::getInstance();
    }

    public function testUntypedParameterWithDefault(): void {
        self::assertSame('default', UntypedSingleton::getInstance()->value);
    }

    public function testDefinedParametersWin(): void {
        self::assertSame(
            ['greeter' => 'given', 'label' => 'default'],
            DefinedParametersSingleton::getParameters(['greeter' => 'given'])
        );
    }
}

final class NotServiceSingleton extends Singleton {
    public function __construct(
        public \Countable $countable
    ) {}
}

final class UntypedSingleton extends Singleton {
    /**
     * @param mixed $value
     */
    public function __construct(
        public $value = 'default'
    ) {}
}

final class DefinedParametersSingleton extends Singleton {
    /**
     * @param array<string, mixed> $definedParameters
     *
     * @return array<string, mixed>
     */
    public static function getParameters(array $definedParameters): array {
        return static::getConstructorParameters(SingletonService::class, $definedParameters);
    }
}

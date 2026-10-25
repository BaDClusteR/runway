<?php

declare(strict_types=1);

namespace Runway\Tests\Support;

use ReflectionProperty;
use Runway\DataStorage\IDataStorageDriver;
use Runway\Singleton\Container;

/**
 * The container is a singleton: a test that changes it starts with a new one.
 */
final class ContainerState {
    /**
     * A new container whose data storage driver (and so the query builders of the models) is the given one.
     */
    public static function withDataStorageDriver(IDataStorageDriver $driver): Container {
        $container = Container::resetInstance();
        new ReflectionProperty(Container::class, 'dataStorageDriver')->setValue($container, $driver);

        return $container;
    }

    public static function reset(): Container {
        return Container::resetInstance();
    }
}

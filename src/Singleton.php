<?php

declare(strict_types=1);

namespace Runway;

use Runway\Trait\ConstructorParametersTrait;
//
class Singleton implements ISingleton {
    use ConstructorParametersTrait;

    /**
     * Array of instances for all derived classes
     */
    protected static array $instances = [];

    /**
     * @return static
     */
    public static function getInstance(): static {
        $className = static::class;

        if (!isset(static::$instances[$className])) {
            static::$instances[$className] = new $className(
                ...static::getConstructorParameters(static::class)
            );
        }

        return static::$instances[$className];
    }

    /**
     * @return static
     */
    public static function resetInstance(): static {
        unset(static::$instances[static::class]);

        return static::getInstance();
    }
}
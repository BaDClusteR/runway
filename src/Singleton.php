<?php

declare(strict_types=1);

namespace Runway;

use Runway\Trait\ConstructorParametersTrait;

class Singleton implements ISingleton {
    use ConstructorParametersTrait;

    /**
     * Array of instances for all derived classes
     *
     * @var array<class-string<Singleton>, Singleton>
     */
    protected static array $instances = [];

    public static function getInstance(): static {
        $instance = static::$instances[static::class] ?? null;

        if (!$instance instanceof static) {
            $instance = new \ReflectionClass(static::class)->newInstanceArgs(
                static::getConstructorParameters(static::class)
            );

            static::$instances[static::class] = $instance;
        }

        return $instance;
    }

    public static function resetInstance(): static {
        unset(static::$instances[static::class]);

        return static::getInstance();
    }
}

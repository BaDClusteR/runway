<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

use Runway\Singleton;

/**
 * A singleton service: the container gives its only instance, the constructor gets the services.
 */
class SingletonService extends Singleton {
    public function __construct(
        public readonly IGreeter $greeter,
        public readonly string   $label = 'default'
    ) {}
}

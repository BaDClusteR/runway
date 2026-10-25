<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

/**
 * The way into the circular dependency of CircularA and CircularB: it's not a part of the cycle.
 */
final readonly class CircularEntry {
    public function __construct(
        public CircularA $a
    ) {}
}

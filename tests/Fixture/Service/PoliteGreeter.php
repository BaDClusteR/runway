<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

/**
 * The second (outer) decorator of IGreeter.
 */
final readonly class PoliteGreeter implements IGreeter {
    public function __construct(
        public IGreeter $inner
    ) {}

    public function greet(string $name): string {
        return 'Please, ' . $this->inner->greet($name);
    }
}

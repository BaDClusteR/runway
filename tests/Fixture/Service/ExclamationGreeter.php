<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

/**
 * The first decorator of IGreeter: the inner service comes by the type of the decorated one.
 */
final readonly class ExclamationGreeter implements IGreeter {
    public function __construct(
        public IGreeter $inner
    ) {}

    public function greet(string $name): string {
        return $this->inner->greet($name) . '!';
    }
}

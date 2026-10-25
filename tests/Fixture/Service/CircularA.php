<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

final readonly class CircularA {
    public function __construct(
        public CircularB $b
    ) {}
}

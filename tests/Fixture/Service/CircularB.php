<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

final readonly class CircularB {
    public function __construct(
        public CircularA $a
    ) {}
}

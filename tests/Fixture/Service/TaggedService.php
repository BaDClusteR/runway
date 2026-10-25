<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

final readonly class TaggedService {
    public function __construct(
        public string $name
    ) {}
}

<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

use Countable;

/**
 * Countable is not a service.
 */
final readonly class MissingDependencyService {
    public function __construct(
        public Countable $countable
    ) {}
}

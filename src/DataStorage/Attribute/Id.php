<?php

declare(strict_types=1);

namespace Runway\DataStorage\Attribute;

use Attribute;

/**
 * The attribute marks property as the one that corresponds to the primary column in data storage.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Id {
    public function __construct(
        public string $name = "id"
    ) {}
}

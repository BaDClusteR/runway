<?php

declare(strict_types=1);

namespace Runway\DataStorage\Attribute;

use Attribute;

/**
 * The attribute marks that the given property stores the value from data storage.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Column {
    public function __construct(
        public ?string $name = null,
        public ?string $type = null
    ) {}
}

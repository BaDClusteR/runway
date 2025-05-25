<?php

declare(strict_types=1);

namespace Runway\DataStorage\Attribute;

use Attribute;

/**
 * The attribute marks the property as an array of lazy-loaded AbstractType models.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Reference {
    public function __construct(
        public string $refModel,
        public string $refProp,
        public array  $refOrderBy = []
    ) {}
}

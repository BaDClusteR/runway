<?php

declare(strict_types=1);

namespace Runway\DataStorage\Attribute;

use Attribute;

/**
 * The attribute marks that the property should not have a default setter.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class NoSetter {
}

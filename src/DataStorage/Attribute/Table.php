<?php

declare(strict_types=1);

namespace Runway\DataStorage\Attribute;

use Attribute;

/**
 * The attribute marks the table for the given class.
 */
#[Attribute(Attribute::TARGET_CLASS)]
readonly class Table {
    /**
     * @param string $tableName Table name (w/o prefix).
     */
    public function __construct(
        public string $tableName
    ) {}
}

<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Exception;

use Throwable;

class UnknownOperatorException extends QueryBuilderException {
    public function __construct(
        string     $comparator,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("Unknown comparator: $comparator", $code, $previous);
    }
}
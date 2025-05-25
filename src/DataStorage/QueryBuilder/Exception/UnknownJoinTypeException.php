<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Exception;

use Throwable;

class UnknownJoinTypeException extends QueryBuilderException {
    public function __construct(
        string     $joinType,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("Unknown join type: {$joinType}", $code, $previous);
    }
}
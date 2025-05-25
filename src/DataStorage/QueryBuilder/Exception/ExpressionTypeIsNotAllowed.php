<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Exception;

use Throwable;

class ExpressionTypeIsNotAllowed extends InvalidArgumentException {
    public function __construct(
        string     $expressionType = "",
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("Expression type is not allowed here: {$expressionType}", $code, $previous);
    }
}
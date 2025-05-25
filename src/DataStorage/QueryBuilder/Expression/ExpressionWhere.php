<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

class ExpressionWhere extends AExpressionCondition {
    protected string $prefix = "WHERE ";
}
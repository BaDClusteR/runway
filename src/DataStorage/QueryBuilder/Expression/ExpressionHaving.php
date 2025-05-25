<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

class ExpressionHaving extends AExpressionCondition {
    protected string $prefix = "HAVING ";
}
<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

class ExpressionIsNotNull implements IExpression {
    public function __construct(
        protected string|AExpressionBoolean|ExpressionMath|ExpressionFunc $expr
    ) {}

    public function __toString(): string {
        return "({$this->expr} IS NOT NULL)";
    }
}
<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

class ExpressionMath implements IExpression {
    public function __construct(
        protected string|ExpressionMath|ExpressionFunc|AExpressionBoolean $leftPart,
        protected string                                                  $operator,
        protected string|ExpressionMath|ExpressionFunc|AExpressionBoolean $rightPart
    ) {}

    public function __toString(): string {
        return "({$this->leftPart} {$this->operator} {$this->rightPart})";
    }
}
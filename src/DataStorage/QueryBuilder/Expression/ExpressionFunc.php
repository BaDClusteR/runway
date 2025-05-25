<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

class ExpressionFunc implements IExpression {
    public function __construct(
        protected string $name,
        protected array  $args
    ) {}

    public function __toString(): string {
        return "{$this->name}(" . implode(', ', $this->args) . ")";
    }
}
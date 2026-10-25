<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Stringable;

class ExpressionFunc implements IExpression {
    /**
     * @param array<mixed> $args SQL: the columns, the query variables, the expressions.
     */
    public function __construct(
        protected string $name,
        protected array  $args
    ) {}

    public function __toString(): string {
        return "{$this->name}(" . implode(', ', array_map(
            static fn(mixed $arg): string => (is_scalar($arg) || $arg instanceof Stringable) ? (string)$arg : '',
            $this->args
        )) . ")";
    }
}

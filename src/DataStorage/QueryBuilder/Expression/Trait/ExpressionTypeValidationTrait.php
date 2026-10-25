<?php

namespace Runway\DataStorage\QueryBuilder\Expression\Trait;

use Runway\DataStorage\QueryBuilder\Exception\ExpressionTypeIsNotAllowed;
use Runway\DataStorage\QueryBuilder\Expression\IExpression;

trait ExpressionTypeValidationTrait {
    /**
     * A part can be SQL (a string) or an expression of the allowed classes (static::$allowedPartExpressionClasses).
     *
     * @throws ExpressionTypeIsNotAllowed
     */
    protected function validateExpressionPart(mixed $part): void {
        if (
            $part instanceof IExpression
            && !array_any(
                static::$allowedPartExpressionClasses,
                static fn(string $fqn): bool => $part instanceof $fqn
            )
        ) {
            throw new ExpressionTypeIsNotAllowed(get_class($part));
        }
    }

    /**
     * @param array<mixed> $parts
     *
     * @throws ExpressionTypeIsNotAllowed
     */
    protected function validateExpressionParts(array $parts): void {
        foreach ($parts as $part) {
            $this->validateExpressionPart($part);
        }
    }
}

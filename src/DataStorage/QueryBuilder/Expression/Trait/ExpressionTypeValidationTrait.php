<?php

namespace Runway\DataStorage\QueryBuilder\Expression\Trait;

use Runway\DataStorage\QueryBuilder\Exception\ExpressionTypeIsNotAllowed;
use Runway\DataStorage\QueryBuilder\Expression\IExpression;

trait ExpressionTypeValidationTrait {
    /**
     * @throws ExpressionTypeIsNotAllowed
     */
    protected function validateExpressionPart($part): void {
        if ($part instanceof IExpression) {
            $validated = false;

            foreach (static::$allowedPartExpressionClasses as $fqn) {
                if ($part instanceof $fqn) {
                    $validated = true;
                    break;
                }
            }

            if (!$validated) {
                throw new ExpressionTypeIsNotAllowed(get_class($part));
            }
        }
    }

    /**
     * @throws ExpressionTypeIsNotAllowed
     */
    protected function validateExpressionParts($parts): void {
        array_walk(
            $parts,
            function ($part) {
                $this->validateExpressionPart($part);
            }
        );
    }
}
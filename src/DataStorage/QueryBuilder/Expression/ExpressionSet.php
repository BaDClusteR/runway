<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

/**
 * The parts: the field and its value (SQL, e.g. a query variable ":name").
 *
 * @extends AExpressionComposite<array{0: string, 1: string|int|ExpressionMath|ExpressionFunc}>
 */
class ExpressionSet extends AExpressionComposite {
    protected string $prefix = "SET ";

    protected string $postfix = "";

    protected function convertPart(mixed $part): string {
        return "`{$part[0]}` = {$part[1]}";
    }

    public function add(
        string                                   $fieldName,
        string|int|ExpressionMath|ExpressionFunc $value
    ): static {
        return $this->addPart([
            $fieldName,
            $value
        ]);
    }
}

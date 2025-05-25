<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\Expression\Trait\ExpressionCompositeDynamicArgsTrait;

class ExpressionGroupBy extends AExpressionComposite {
    use ExpressionCompositeDynamicArgsTrait;

    protected string $prefix = "GROUP BY ";

    protected string $postfix = "";

    public function add(string $field): static {
        return $this->addPart($field);
    }
}
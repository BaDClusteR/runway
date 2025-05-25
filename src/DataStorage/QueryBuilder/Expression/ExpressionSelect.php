<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\Expression\Trait\ExpressionCompositeDynamicArgsTrait;

class ExpressionSelect extends AExpressionComposite {
    use ExpressionCompositeDynamicArgsTrait;
}
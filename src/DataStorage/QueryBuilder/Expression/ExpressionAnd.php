<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\Exception\ExpressionTypeIsNotAllowed;
use Runway\DataStorage\QueryBuilder\Expression\Trait\ExpressionCompositeDynamicArgsTrait;

class ExpressionAnd extends AExpressionBoolean {
    use ExpressionCompositeDynamicArgsTrait;

    protected static array $allowedPartExpressionClasses = [
        AExpressionBoolean::class,
        ExpressionComparison::class,
        ExpressionFunc::class,
        ExpressionMath::class,
    ];

    protected string $separator = " AND ";

    /**
     * @throws ExpressionTypeIsNotAllowed
     */
    public function add($part): static {
        $this->validateExpressionPart($part);

        return $this->addPart($part);
    }
}
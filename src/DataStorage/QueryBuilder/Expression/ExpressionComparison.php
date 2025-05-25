<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\Converter\IComparisonOperatorConverter;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionComparisonOperatorsEnum;
use Runway\Service\Exception\ServiceException;
use Runway\Singleton\Container;

class ExpressionComparison implements IExpression {
    protected IComparisonOperatorConverter $comparisonOperatorConverter;

    /**
     * @throws ServiceException
     */
    public function __construct(
        protected string|int|bool|ExpressionComparison|AExpressionBoolean|ExpressionFunc|ExpressionMath $leftPart,
        protected ExpressionComparisonOperatorsEnum                                                     $operator,
        protected string|int|bool|ExpressionComparison|AExpressionBoolean|ExpressionFunc|ExpressionMath $rightPart,
    ) {
        $this->comparisonOperatorConverter = Container::getInstance()->getService(IComparisonOperatorConverter::class);
    }

    public function __toString(): string {
        $operator = $this->comparisonOperatorConverter->toString($this->operator);

        $rightPart = ($this->operator === ExpressionComparisonOperatorsEnum::IN)
            ? "({$this->rightPart})"
            : $this->rightPart;

        return "{$this->leftPart} {$operator} {$rightPart}";
    }
}
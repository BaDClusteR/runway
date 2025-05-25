<?php

namespace Runway\DataStorage\QueryBuilder\Converter;

use Runway\DataStorage\QueryBuilder\Enum\ExpressionComparisonOperatorsEnum;
use Runway\DataStorage\QueryBuilder\Exception\UnknownOperatorException;

interface IComparisonOperatorConverter {
    public function toString(ExpressionComparisonOperatorsEnum $operator): string;

    /**
     * @throws UnknownOperatorException
     */
    public function fromString(string $operator): ExpressionComparisonOperatorsEnum;
}
<?php

namespace Runway\DataStorage\QueryBuilder\Converter;

use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinConditionTypeEnum;
use Runway\DataStorage\QueryBuilder\Exception\UnknownJoinConditionTypeException;

interface IJoinConditionTypeConverter {
    public function toString(ExpressionJoinConditionTypeEnum $joinConditionType): string;

    /**
     * @throws UnknownJoinConditionTypeException
     */
    public function fromString(string $joinConditionType): ExpressionJoinConditionTypeEnum;
}
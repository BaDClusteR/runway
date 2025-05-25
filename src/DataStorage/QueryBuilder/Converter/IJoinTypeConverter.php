<?php

namespace Runway\DataStorage\QueryBuilder\Converter;

use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinTypeEnum;
use Runway\DataStorage\QueryBuilder\Exception\UnknownJoinTypeException;

interface IJoinTypeConverter {
    public function toString(ExpressionJoinTypeEnum $joinType): string;

    /**
     * @throws UnknownJoinTypeException
     */
    public function fromString(string $joinType): ExpressionJoinTypeEnum;
}
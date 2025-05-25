<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Converter;

use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinConditionTypeEnum;
use Runway\DataStorage\QueryBuilder\Exception\UnknownJoinConditionTypeException;

class JoinConditionTypeConverter implements IJoinConditionTypeConverter {
    public function toString(ExpressionJoinConditionTypeEnum $joinConditionType): string {
        return match ($joinConditionType) {
            ExpressionJoinConditionTypeEnum::ON   => "ON",
            ExpressionJoinConditionTypeEnum::WITH => "WITH",
        };
    }

    /**
     * @throws UnknownJoinConditionTypeException
     */
    public function fromString(string $joinConditionType): ExpressionJoinConditionTypeEnum {
        return match (strtoupper(trim($joinConditionType))) {
            "ON"    => ExpressionJoinConditionTypeEnum::ON,
            "WITH"  => ExpressionJoinConditionTypeEnum::WITH,
            default => throw new UnknownJoinConditionTypeException($joinConditionType)
        };
    }
}
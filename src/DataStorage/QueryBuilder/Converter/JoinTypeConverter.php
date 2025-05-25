<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Converter;

use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinTypeEnum;
use Runway\DataStorage\QueryBuilder\Exception\UnknownJoinTypeException;

class JoinTypeConverter implements IJoinTypeConverter {
    public function toString(ExpressionJoinTypeEnum $joinType): string {
        return match ($joinType) {
            ExpressionJoinTypeEnum::INNER_JOIN => "INNER",
            ExpressionJoinTypeEnum::LEFT_JOIN  => "LEFT",
            ExpressionJoinTypeEnum::RIGHT_JOIN => "RIGHT",
            ExpressionJoinTypeEnum::CROSS_JOIN => "CROSS",
            ExpressionJoinTypeEnum::SELF_JOIN  => "SELF"
        };
    }

    /**
     * @throws UnknownJoinTypeException
     */
    public function fromString(string $joinType): ExpressionJoinTypeEnum {
        $joinType = strtoupper(trim($joinType));

        if (str_ends_with($joinType, " JOIN")) {
            $joinType = substr($joinType, 0, -5);
        }

        return match ($joinType) {
            "INNER" => ExpressionJoinTypeEnum::INNER_JOIN,
            "LEFT"  => ExpressionJoinTypeEnum::LEFT_JOIN,
            "RIGHT" => ExpressionJoinTypeEnum::RIGHT_JOIN,
            "CROSS" => ExpressionJoinTypeEnum::CROSS_JOIN,
            "SELF"  => ExpressionJoinTypeEnum::SELF_JOIN,
            default => throw new UnknownJoinTypeException($joinType)
        };
    }
}
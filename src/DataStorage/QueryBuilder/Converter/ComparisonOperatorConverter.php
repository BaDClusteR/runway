<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Converter;

use Runway\DataStorage\QueryBuilder\Enum\ExpressionComparisonOperatorsEnum;
use Runway\DataStorage\QueryBuilder\Exception\UnknownOperatorException;

class ComparisonOperatorConverter implements IComparisonOperatorConverter {
    public function toString(ExpressionComparisonOperatorsEnum $operator): string {
        return match ($operator) {
            ExpressionComparisonOperatorsEnum::GREATER_OR_EQUAL => ">=",
            ExpressionComparisonOperatorsEnum::LESS_OR_EQUAL    => "<=",
            ExpressionComparisonOperatorsEnum::GREATER_THAN     => ">",
            ExpressionComparisonOperatorsEnum::LESS_THAN        => "<",
            ExpressionComparisonOperatorsEnum::EQUAL            => "=",
            ExpressionComparisonOperatorsEnum::NOT_EQUAL        => "<>",
            ExpressionComparisonOperatorsEnum::LIKE             => "IS LIKE",
            ExpressionComparisonOperatorsEnum::NOT_LIKE         => "IS NOT LIKE",
            ExpressionComparisonOperatorsEnum::IN               => "IN",
        };
    }

    /**
     * @throws UnknownOperatorException
     */
    public function fromString(string $operator): ExpressionComparisonOperatorsEnum {
        return match (strtoupper($operator)) {
            ">="                      => ExpressionComparisonOperatorsEnum::GREATER_OR_EQUAL,
            "<="                      => ExpressionComparisonOperatorsEnum::LESS_OR_EQUAL,
            "<"                       => ExpressionComparisonOperatorsEnum::LESS_THAN,
            ">"                       => ExpressionComparisonOperatorsEnum::GREATER_THAN,
            "="                       => ExpressionComparisonOperatorsEnum::EQUAL,
            "<>", "!="                => ExpressionComparisonOperatorsEnum::NOT_EQUAL,
            "IS LIKE", "LIKE"         => ExpressionComparisonOperatorsEnum::LIKE,
            "IS NOT LIKE", "NOT LIKE" => ExpressionComparisonOperatorsEnum::NOT_LIKE,
            "IN"                      => ExpressionComparisonOperatorsEnum::IN,
            default                   => throw new UnknownOperatorException($operator),
        };
    }
}
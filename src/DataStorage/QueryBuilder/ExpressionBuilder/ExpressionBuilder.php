<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\ExpressionBuilder;

use Runway\DataStorage\QueryBuilder\Enum\ExpressionComparisonOperatorsEnum;
use Runway\DataStorage\QueryBuilder\Exception\ExpressionTypeIsNotAllowed;
use Runway\DataStorage\QueryBuilder\Expression\AExpressionBoolean;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionAnd;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionComparison;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionFunc;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionIsNotNull;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionIsNull;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionMath;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionOr;

class ExpressionBuilder implements IExpressionBuilder {
    /**
     * @throws ExpressionTypeIsNotAllowed
     */
    public function and(...$args): ExpressionAnd {
        return new ExpressionAnd(...$args);
    }

    /**
     * @throws ExpressionTypeIsNotAllowed
     */
    public function or(...$args): ExpressionOr {
        return new ExpressionOr(...$args);
    }

    public function eq(
        ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $leftPart,
        ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $rightPart
    ): ExpressionComparison {
        return new ExpressionComparison(
            $leftPart,
            ExpressionComparisonOperatorsEnum::EQUAL,
            $rightPart
        );
    }

    public function neq(
        ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $leftPart,
        ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $rightPart
    ): ExpressionComparison {
        return new ExpressionComparison(
            $leftPart,
            ExpressionComparisonOperatorsEnum::NOT_EQUAL,
            $rightPart
        );
    }

    public function gt(
        ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $leftPart,
        ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $rightPart
    ): ExpressionComparison {
        return new ExpressionComparison(
            $leftPart,
            ExpressionComparisonOperatorsEnum::GREATER_THAN,
            $rightPart
        );
    }

    public function lt(ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $leftPart, ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $rightPart): ExpressionComparison {
        return new ExpressionComparison(
            $leftPart,
            ExpressionComparisonOperatorsEnum::LESS_THAN,
            $rightPart
        );
    }

    public function ge(ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $leftPart, ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $rightPart): ExpressionComparison {
        return new ExpressionComparison(
            $leftPart,
            ExpressionComparisonOperatorsEnum::GREATER_OR_EQUAL,
            $rightPart
        );
    }

    public function le(ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $leftPart, ExpressionFunc|AExpressionBoolean|ExpressionMath|string|int|bool $rightPart): ExpressionComparison {
        return new ExpressionComparison(
            $leftPart,
            ExpressionComparisonOperatorsEnum::LESS_OR_EQUAL,
            $rightPart
        );
    }

    public function in(
        ExpressionFunc|ExpressionMath|string $leftPart,
        ExpressionFunc|ExpressionMath|string|array $rightPart
    ): ExpressionComparison {
        return new ExpressionComparison(
            $leftPart,
            ExpressionComparisonOperatorsEnum::IN,
            is_array($rightPart)
                ? implode(", ", $rightPart)
                : $rightPart
        );
    }

    public function isNull(ExpressionFunc|AExpressionBoolean|ExpressionMath|string $value): ExpressionIsNull {
        return new ExpressionIsNull($value);
    }

    public function isNotNull(ExpressionFunc|AExpressionBoolean|ExpressionMath|string $value): ExpressionIsNotNull {
        return new ExpressionIsNotNull($value);
    }

    public function func(string $funcName, ...$args): ExpressionFunc {
        return new ExpressionFunc($funcName, $args);
    }
}
<?php

namespace Runway\DataStorage\QueryBuilder\ExpressionBuilder;

use Runway\DataStorage\QueryBuilder\Exception\ExpressionTypeIsNotAllowed;
use Runway\DataStorage\QueryBuilder\Expression\AExpressionBoolean;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionAnd;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionComparison;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionFunc;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionIsNotNull;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionIsNull;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionMath;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionOr;

interface IExpressionBuilder {
    /**
     * @throws ExpressionTypeIsNotAllowed
     */
    public function and(...$args): ExpressionAnd;

    /**
     * @throws ExpressionTypeIsNotAllowed
     */
    public function or(...$args): ExpressionOr;

    public function eq(
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $leftPart,
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $rightPart
    ): ExpressionComparison;

    public function neq(
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $leftPart,
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $rightPart
    ): ExpressionComparison;

    public function gt(
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $leftPart,
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $rightPart
    ): ExpressionComparison;

    public function lt(
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $leftPart,
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $rightPart
    ): ExpressionComparison;

    public function ge(
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $leftPart,
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $rightPart
    ): ExpressionComparison;

    public function le(
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $leftPart,
        string|int|bool|AExpressionBoolean|ExpressionFunc|ExpressionMath $rightPart
    ): ExpressionComparison;

    public function in(
        string|ExpressionFunc|ExpressionMath $leftPart,
        string|ExpressionFunc|ExpressionMath $rightPart
    ): ExpressionComparison;

    public function isNull(
        string|AExpressionBoolean|ExpressionFunc|ExpressionMath $value,
    ): ExpressionIsNull;

    public function isNotNull(
        string|AExpressionBoolean|ExpressionFunc|ExpressionMath $value,
    ): ExpressionIsNotNull;

    public function func(string $funcName, ...$args): ExpressionFunc;
}
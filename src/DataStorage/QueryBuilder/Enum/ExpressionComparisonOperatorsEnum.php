<?php

namespace Runway\DataStorage\QueryBuilder\Enum;

enum ExpressionComparisonOperatorsEnum {
    case GREATER_OR_EQUAL;

    case LESS_OR_EQUAL;

    case GREATER_THAN;

    case LESS_THAN;

    case EQUAL;

    case NOT_EQUAL;

    case LIKE;

    case NOT_LIKE;

    case IN;
}

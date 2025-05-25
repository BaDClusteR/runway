<?php

namespace Runway\DataStorage\QueryBuilder\Enum;

enum ExpressionJoinTypeEnum {
    case INNER_JOIN;

    case LEFT_JOIN;

    case RIGHT_JOIN;

    case CROSS_JOIN;

    case SELF_JOIN;
}

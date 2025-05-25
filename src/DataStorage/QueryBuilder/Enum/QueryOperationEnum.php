<?php

namespace Runway\DataStorage\QueryBuilder\Enum;

enum QueryOperationEnum {
    case SELECT;

    case INSERT;

    case UPDATE;

    case DELETE;
}

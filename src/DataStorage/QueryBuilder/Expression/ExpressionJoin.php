<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\Converter\IJoinConditionTypeConverter;
use Runway\DataStorage\QueryBuilder\Converter\IJoinTypeConverter;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinConditionTypeEnum;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinTypeEnum;
use Runway\Singleton\Container;

class ExpressionJoin extends AExpressionComposite {
    protected IJoinTypeConverter $joinTypeConverter;

    protected IJoinConditionTypeConverter $joinConditionTypeConverter;

    protected string $separator = " ";

    /**
     * @var array{
     *     0: ExpressionJoinTypeEnum,
     *     1: string,
     *     2: string,
     *     3: ExpressionJoinConditionTypeEnum,
     *     4: string|ExpressionComparison|ExpressionFunc,
     *     5: string,
     * }[]
     */
    protected array $parts;

    public function __construct() {
        $this->joinTypeConverter = Container::getInstance()->getService(IJoinTypeConverter::class);
        $this->joinConditionTypeConverter = Container::getInstance()->getService(IJoinConditionTypeConverter::class);
    }

    public function add(
        ExpressionJoinTypeEnum                     $joinType,
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static {
        return $this->addPart(
            [
                $joinType,
                $joinTable,
                $alias,
                $joinConditionType,
                $condition,
                $indexBy
            ]
        );
    }

    /**
     * @param array{
     *      0: ExpressionJoinTypeEnum,
     *      1: string,
     *      2: string,
     *      3: ExpressionJoinConditionTypeEnum,
     *      4: string|ExpressionComparison|ExpressionFunc,
     *      5: string,
     *  } $part
     */
    public function convertPart($part): string {
        $joinTypeString = $this->joinTypeConverter->toString($part[0]);

        $result = "{$joinTypeString} JOIN ";

        // Table that is being joined
        if ($part[2]) {
            $result .= " {$part[2]}";
        }

        // Condition
        if ($part[4]) {
            $joinConditionTypeString = $this->joinConditionTypeConverter->toString($part[3]);

            $result .= " {$joinConditionTypeString} ({$part[4]})";
        }

        // Index by
        if ($part[5]) {
            $result .= " INDEX BY {$part[5]}";
        }

        return $result;
    }
}
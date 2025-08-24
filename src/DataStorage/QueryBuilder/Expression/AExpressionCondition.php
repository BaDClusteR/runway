<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\Enum\ExpressionWherePartTypeEnum;

class AExpressionCondition extends AExpressionComposite {
    protected string $postfix = "";

    protected string $separator = "";

    public function __construct(
        ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $where
    ) {
        $this->addPart([
            null,
            $where
        ]);
    }

    public function addAnd(
        ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $part
    ): static {
        return $this->addPart([
            ExpressionWherePartTypeEnum::AND,
            $part
        ]);
    }

    public function addOr(
        ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $part
    ): static {
        return $this->addPart([
            ExpressionWherePartTypeEnum::OR,
            $part
        ]);
    }

    /**
     * @param array{
     *     0: ExpressionWherePartTypeEnum|null,
     *     1: ExpressionComparison|AExpressionBoolean
     * } $part
     */
    public function convertPart($part): string {
        return $this->getWherePartType($part[0] ?? null)
            . ($part[1] ?? "");
    }

    public function getWherePartType(?ExpressionWherePartTypeEnum $partType): string {
        return match ($partType) {
            ExpressionWherePartTypeEnum::AND => " AND ",
            ExpressionWherePartTypeEnum::OR  => " OR ",
            default                          => ""
        };
    }
}
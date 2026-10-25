<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\DTO\ExpressionOrderPartDTO;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionOrderDirectionEnum;

/**
 * @extends AExpressionComposite<ExpressionOrderPartDTO>
 */
class ExpressionOrderBy extends AExpressionComposite {
    protected string $prefix = "ORDER BY ";

    protected string $postfix = "";

    public function __construct(string $field, string $direction) {
        $this->add($field, $direction);
    }

    /**
     * @param string $direction "DESC" (in any case), ASC otherwise: it's not a part of the SQL.
     */
    public function add(string $field, string $direction = "ASC"): static {
        return $this->addPart(
            new ExpressionOrderPartDTO(
                $field,
                (strtoupper(trim($direction)) === "DESC")
                    ? ExpressionOrderDirectionEnum::DESC
                    : ExpressionOrderDirectionEnum::ASC
            )
        );
    }

    protected function convertPart(mixed $part): string {
        return "{$part->getField()} {$part->getDirection()}";
    }
}

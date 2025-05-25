<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\DTO\ExpressionOrderPartDTO;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionOrderDirectionEnum;

class ExpressionOrderBy extends AExpressionComposite {
    /**
     * @var ExpressionOrderPartDTO[]
     */
    protected array $parts;

    protected string $prefix = "ORDER BY ";

    protected string $postfix = "";

    public function __construct(string $field, string $direction) {
        $this->add($field, $direction);
    }

    public function add(string $field, string $direction = "ASC"): static {
        return $this->addPart(
            new ExpressionOrderPartDTO(
                $field,
                ($direction === "DESC")
                    ? ExpressionOrderDirectionEnum::DESC
                    : ExpressionOrderDirectionEnum::ASC
            )
        );
    }

    /**
     * @param ExpressionOrderPartDTO $part
     */
    protected function convertPart($part): string {
        return "{$part->getField()} {$part->getDirection()}";
    }
}
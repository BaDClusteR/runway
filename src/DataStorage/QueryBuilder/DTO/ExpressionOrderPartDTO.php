<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\DTO;

use Runway\DataStorage\QueryBuilder\Enum\ExpressionOrderDirectionEnum;

readonly class ExpressionOrderPartDTO {
    public function __construct(
        private string                       $field,
        private ExpressionOrderDirectionEnum $direction
    ) {}

    public function getField(): string {
        return $this->field;
    }

    public function getDirection(): string {
        return $this->direction === ExpressionOrderDirectionEnum::ASC
            ? "ASC"
            : "DESC";
    }
}
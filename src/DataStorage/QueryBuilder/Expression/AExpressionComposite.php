<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\Expression\Trait\ExpressionTypeValidationTrait;

abstract class AExpressionComposite implements IExpression {
    use ExpressionTypeValidationTrait;

    protected array $parts = [];

    protected string $separator = ", ";

    protected string $prefix = "(";

    protected string $postfix = ")";

    /**
     * @var string[]
     */
    protected static array $allowedPartExpressionClasses = [];

    protected function addPart($part): static {
        $this->parts[] = $part;

        return $this;
    }

    public function getParts(): array {
        return $this->parts;
    }

    protected function convertPart($part): string {
        return (string)$part;
    }

    public function __toString(): string {
        return $this->parts
            ? (
                $this->prefix
                . implode(
                    $this->separator,
                    array_map(
                        fn($part): string => $this->convertPart($part),
                        $this->parts
                    )
                )
                . $this->postfix
            )
            : "";
    }

    public function reset(): static {
        $this->parts = [];

        return $this;
    }
}
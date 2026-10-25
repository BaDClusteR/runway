<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\Expression\Trait\ExpressionTypeValidationTrait;
use Stringable;

/**
 * @template TPart The type of the parts (e.g. the field and the value of SET).
 */
abstract class AExpressionComposite implements IExpression {
    use ExpressionTypeValidationTrait;

    /**
     * @var array<TPart>
     */
    protected array $parts = [];

    protected string $separator = ", ";

    protected string $prefix = "(";

    protected string $postfix = ")";

    /**
     * @var class-string<IExpression>[] The expressions that can be parts (see ExpressionTypeValidationTrait).
     */
    protected static array $allowedPartExpressionClasses = [];

    /**
     * @param TPart $part
     */
    protected function addPart(mixed $part): static {
        $this->parts[] = $part;

        return $this;
    }

    /**
     * @return array<TPart>
     */
    public function getParts(): array {
        return $this->parts;
    }

    /**
     * @param TPart $part
     */
    protected function convertPart(mixed $part): string {
        return (is_scalar($part) || $part instanceof Stringable) ? (string)$part : "";
    }

    public function __toString(): string {
        return $this->parts
            ? (
                $this->prefix
                . implode(
                    $this->separator,
                    array_map(
                        fn(mixed $part): string => $this->convertPart($part),
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

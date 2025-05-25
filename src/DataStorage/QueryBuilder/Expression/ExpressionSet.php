<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

class ExpressionSet extends AExpressionComposite {
    /**
     * @var array{0: string, 1:string|int|ExpressionMath|ExpressionFunc}[]
     */
    protected array $parts;

    protected string $prefix = "SET ";

    protected string $postfix = "";

    /**
     * @param array{0: string, 1:string|int|ExpressionMath|ExpressionFunc} $part
     */
    public function convertPart($part): string {
        return "{$part[0]} = {$part[1]}";
    }

    public function add(
        string                                   $fieldName,
        string|int|ExpressionMath|ExpressionFunc $value
    ): static {
        $this->addPart([
            $fieldName,
            $value
        ]);

        return $this;
    }
}
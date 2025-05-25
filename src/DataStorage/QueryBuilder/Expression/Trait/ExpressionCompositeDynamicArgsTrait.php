<?php

namespace Runway\DataStorage\QueryBuilder\Expression\Trait;

use Runway\DataStorage\QueryBuilder\Exception\ExpressionTypeIsNotAllowed;

trait ExpressionCompositeDynamicArgsTrait {
    /**
     * @throws ExpressionTypeIsNotAllowed
     */
    public function __construct(...$args) {
        $this->validateExpressionParts($args);

        $this->parts = $args;
    }
}
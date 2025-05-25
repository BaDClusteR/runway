<?php

namespace Runway\DataStorage\QueryBuilder\Expression;

interface IExpression {
    public function __toString(): string;
}
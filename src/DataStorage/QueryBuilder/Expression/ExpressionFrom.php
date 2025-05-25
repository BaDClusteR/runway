<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Expression;

use Runway\DataStorage\QueryBuilder\Converter\ITableNameEscaper;
use Runway\Singleton\Container;

class ExpressionFrom implements IExpression {
    public function __construct(
        protected string $tableName,
        protected string $alias = '',
        protected string $indexBy = ''
    ) {}

    public function __toString(): string {
        $result = "FROM " . $this->getTableNameEscaper()->escapeTableName($this->tableName);

        if ($this->alias) {
            $result .= " {$this->alias}";
        }

        if ($this->indexBy) {
            $result .= " INDEX BY {$this->indexBy}";
        }

        return $result;
    }

    protected function getTableNameEscaper(): ITableNameEscaper {
        return Container::getInstance()->getService(ITableNameEscaper::class);
    }
}
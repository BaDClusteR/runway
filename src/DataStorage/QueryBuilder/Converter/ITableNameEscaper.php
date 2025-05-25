<?php

namespace Runway\DataStorage\QueryBuilder\Converter;

interface ITableNameEscaper {
    public function escapeTableName(string $tableName): string;
}
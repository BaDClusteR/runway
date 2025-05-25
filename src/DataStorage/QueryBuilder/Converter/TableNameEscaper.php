<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder\Converter;

class TableNameEscaper implements ITableNameEscaper {

    public function escapeTableName(string $tableName): string {
        return $tableName
            ? "`{{$tableName}}`"
            : "";
    }
}
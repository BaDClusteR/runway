<?php

namespace Runway\DataStorage\QueryBuilder;

use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\IDataStorageDriver;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinConditionTypeEnum;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
use Runway\DataStorage\QueryBuilder\Expression\AExpressionBoolean;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionComparison;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionFunc;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionIsNull;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionMath;
use Runway\DataStorage\QueryBuilder\ExpressionBuilder\IExpressionBuilder;

interface IQueryBuilder {
    public function setDataStorageDriver(IDataStorageDriver $dataStorageDriver): static;

    public function getSQL(): string;

    public function select(...$args): static;

    public function from(string $tableName, string $alias = '', string $indexBy = ''): static;

    public function clearFrom(): static;

    public function leftJoin(
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static;

    public function rightJoin(
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static;

    public function innerJoin(
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static;

    public function crossJoin(
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static;

    public function selfJoin(
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static;

    public function clearJoin(): static;

    public function where(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static;

    public function andWhere(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static;

    public function orWhere(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static;

    public function clearWhere(): static;

    public function having(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static;

    public function andHaving(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static;

    public function orHaving(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static;

    public function clearHaving(): static;

    public function groupBy(string $field): static;

    public function addGroupBy(string $field): static;

    public function clearGroupBy(): static;

    public function orderBy(string $field, string $direction): static;

    public function addOrderBy(string $field, string $direction): static;

    public function clearOrderBy(): static;

    public function setLimit(int $limit, ?int $offset = null): static;

    public function clearLimit(): static;

    public function insert(): static;

    public function into(string $tableName): static;

    public function values(array $values): static;

    public function addValue(array $value): static;

    public function addValues(array $values): static;

    public function clearValues(): static;

    public function update(string $tableName): static;

    public function set(string $fieldName, string|int|ExpressionMath|ExpressionFunc $value): static;

    public function addSet(string $fieldName, string|int|ExpressionMath|ExpressionFunc $value): static;

    public function clearSet(): static;

    public function delete(): static;

    public function setVariable(string $name, mixed $value): static;

    public function setVariables(array $values): static;

    public function clearVariables(): static;

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getResult(): array;

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getFirstResult(): array;

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getFirstScalarResult(): mixed;

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function iterate(): iterable;

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function execute(): void;

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function count(): int;

    public function getLastInsertId(): string;

    public function expr(): IExpressionBuilder;
}
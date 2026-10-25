<?php

namespace Runway\DataStorage\QueryBuilder;

use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\IDataStorageDriver;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinConditionTypeEnum;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
use Runway\DataStorage\QueryBuilder\Expression\AExpressionBoolean;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionComparison;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionFunc;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionIsNotNull;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionIsNull;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionMath;
use Runway\DataStorage\QueryBuilder\ExpressionBuilder\IExpressionBuilder;
use Runway\Model\AEntity;
use Runway\Model\Exception\ModelException;

interface IQueryBuilder {
    public function setDataStorageDriver(IDataStorageDriver $dataStorageDriver): static;

    public function getSQL(): string;

    /**
     * @param string|\Stringable ...$args The columns, "*" if none.
     */
    public function select(string|\Stringable ...$args): static;

    public function from(string $tableNameOrEntityFQN, string $alias = '', string $indexBy = ''): static;

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

    public function where(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static;

    public function andWhere(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static;

    public function orWhere(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static;

    public function clearWhere(): static;

    public function having(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static;

    public function andHaving(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static;

    public function orHaving(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static;

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

    /**
     * @param string[]|null $columns The columns of the rows (see values()), the current ones if null.
     */
    public function into(string $tableName, ?array $columns = null): static;

    /**
     * @param string[] $columns
     */
    public function columns(array $columns): static;

    public function addColumn(string $name): static;

    /**
     * @param string[] $columns
     */
    public function addColumns(array $columns): static;

    /**
     * @param array<array<string|int|float>> $values The rows of INSERT: SQL, e.g. the query variables [":name", ":age"].
     */
    public function values(array $values): static;

    /**
     * @param array<string|int|float> $value A row.
     */
    public function addValue(array $value): static;

    /**
     * @param array<array<string|int|float>> $values The rows.
     */
    public function addValues(array $values): static;

    public function clearValues(): static;

    public function update(string $tableName): static;

    public function set(string $fieldName, string|int|ExpressionMath|ExpressionFunc $value): static;

    public function addSet(string $fieldName, string|int|ExpressionMath|ExpressionFunc $value): static;

    public function clearSet(): static;

    public function delete(): static;

    public function setVariable(string $name, mixed $value): static;

    /**
     * @param array<string, mixed> $values Name => value.
     */
    public function setVariables(array $values): static;

    public function clearVariables(): static;

    /**
     * @return array<array<string, mixed>>
     *
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getResults(): array;

    /**
     * @return AEntity[]
     *
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getEntities(): array;

    /**
     * @return iterable<AEntity>
     *
     * @throws ModelException
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function iterateEntities(): iterable;

    /**
     * @throws DBException
     * @throws QueryBuilderException
     * @throws ModelException
     */
    public function getFirstEntity(): ?AEntity;

    /**
     * @return array<string, mixed>
     *
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
     * @return iterable<array<string, mixed>>
     *
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

    /**
     * @param string $entityFQN An AEntity subclass, '' for none.
     *
     * @throws QueryBuilderException
     */
    public function setEntityFQN(string $entityFQN): static;

    /**
     * @return class-string<AEntity>|''
     */
    public function getEntityFQN(): string;
}
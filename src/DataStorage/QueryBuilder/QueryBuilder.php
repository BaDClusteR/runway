<?php

declare(strict_types=1);

namespace Runway\DataStorage\QueryBuilder;

use Runway\DataStorage\Exception\DBConnectionException;
use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\IDataStorageDriver;
use Runway\DataStorage\QueryBuilder\Converter\ITableNameEscaper;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinConditionTypeEnum;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinTypeEnum;
use Runway\DataStorage\QueryBuilder\Enum\QueryOperationEnum;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
use Runway\DataStorage\QueryBuilder\Expression\AExpressionBoolean;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionComparison;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionFrom;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionFunc;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionGroupBy;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionHaving;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionIsNull;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionJoin;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionMath;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionOrderBy;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionSet;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionWhere;
use Runway\DataStorage\QueryBuilder\ExpressionBuilder\IExpressionBuilder;
use Runway\Singleton\Container;

class QueryBuilder implements IQueryBuilder {
    protected QueryOperationEnum $operation = QueryOperationEnum::SELECT;

    /**
     * @var string[]
     */
    protected array $select = [];

    protected string $tableName = '';

    protected ?ExpressionSet $set = null;

    protected ?ExpressionFrom $from = null;

    protected ?ExpressionJoin $join = null;

    protected ?ExpressionWhere $where = null;

    protected ?ExpressionHaving $having = null;

    protected ?ExpressionGroupBy $groupBy = null;

    protected ?ExpressionOrderBy $orderBy = null;

    /**
     * @var array<string, mixed>
     */
    protected array $variables = [];

    protected array $values = [];

    /**
     * @var array{0?: int, 1?: int}
     */
    protected array $limit = [];

    protected ?IDataStorageDriver $dataStorageDriver = null;

    /**
     * @throws DBConnectionException
     */
    public function __construct(
        protected ITableNameEscaper  $tableNameEscaper,
        protected IExpressionBuilder $expressionBuilder
    ) {
        $this->dataStorageDriver = Container::getInstance()->getDataStorageDriver();
    }

    public function expr(): IExpressionBuilder {
        return $this->expressionBuilder;
    }

    public function setDataStorageDriver(IDataStorageDriver $dataStorageDriver): static {
        $this->dataStorageDriver = $dataStorageDriver;

        return $this;
    }

    public function getSQL(): string {
        return match ($this->operation) {
            QueryOperationEnum::SELECT => $this->compileSelect(),
            QueryOperationEnum::INSERT => $this->compileInsert(),
            QueryOperationEnum::UPDATE => $this->compileUpdate(),
            QueryOperationEnum::DELETE => $this->compileDelete()
        };
    }

    /**
     * @param string[] $args
     */
    public function select(...$args): static {
        $this->operation = QueryOperationEnum::SELECT;
        $this->select = $args ?: ["*"];

        return $this;
    }

    public function insert(): static {
        $this->operation = QueryOperationEnum::INSERT;

        return $this;
    }

    public function into(string $tableName): static {
        $this->tableName = $tableName;

        return $this;
    }

    public function update(string $tableName): static {
        $this->operation = QueryOperationEnum::UPDATE;
        $this->tableName = $tableName;

        return $this;
    }

    public function delete(): static {
        $this->operation = QueryOperationEnum::DELETE;

        return $this;
    }

    public function from(string $tableName, string $alias = '', string $indexBy = ''): static {
        $this->from = new ExpressionFrom(
            $tableName,
            $alias,
            $indexBy,
        );

        return $this;
    }

    public function set(string $fieldName, string|int|ExpressionMath|ExpressionFunc $value): static {
        $this->set = ($this->set ?? new ExpressionSet())
            ->reset()
            ->add($fieldName, $value);

        return $this;
    }

    public function addSet(string $fieldName, string|int|ExpressionMath|ExpressionFunc $value): static {
        $this->set = ($this->set ?? new ExpressionSet())->add($fieldName, $value);

        return $this;
    }

    public function leftJoin(
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static {
        return $this->join(
            ExpressionJoinTypeEnum::LEFT_JOIN,
            $joinTable,
            $alias,
            $joinConditionType,
            $condition,
            $indexBy
        );
    }

    public function rightJoin(
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static {
        return $this->join(
            ExpressionJoinTypeEnum::RIGHT_JOIN,
            $joinTable,
            $alias,
            $joinConditionType,
            $condition,
            $indexBy
        );
    }

    public function innerJoin(
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static {
        return $this->join(
            ExpressionJoinTypeEnum::INNER_JOIN,
            $joinTable,
            $alias,
            $joinConditionType,
            $condition,
            $indexBy
        );
    }

    public function crossJoin(
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static {
        return $this->join(
            ExpressionJoinTypeEnum::CROSS_JOIN,
            $joinTable,
            $alias,
            $joinConditionType,
            $condition,
            $indexBy
        );
    }

    public function selfJoin(
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static {
        return $this->join(
            ExpressionJoinTypeEnum::SELF_JOIN,
            $joinTable,
            $alias,
            $joinConditionType,
            $condition,
            $indexBy
        );
    }

    protected function join(
        ExpressionJoinTypeEnum                     $joinType,
        string                                     $joinTable,
        string                                     $alias = "",
        ExpressionJoinConditionTypeEnum            $joinConditionType = ExpressionJoinConditionTypeEnum::ON,
        string|ExpressionComparison|ExpressionFunc $condition = "",
        string                                     $indexBy = ""
    ): static {
        $this->join = ($this->join ?? new ExpressionJoin())
            ->add($joinType, $joinTable, $alias, $joinConditionType, $condition, $indexBy);

        return $this;
    }

    public function where(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static {
        $this->where = new ExpressionWhere($condition);

        return $this;
    }

    public function andWhere(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static {
        $this->where = $this->where
            ? $this->where->addAnd($condition)
            : new ExpressionWhere($condition);

        return $this;
    }

    public function orWhere(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static {
        $this->where = $this->where
            ? $this->where->addOr($condition)
            : new ExpressionWhere($condition);

        return $this;
    }

    public function having(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static {
        $this->having = new ExpressionHaving($condition);

        return $this;
    }

    public function andHaving(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static {
        $this->having = $this->having
            ? $this->having->addAnd($condition)
            : new ExpressionHaving($condition);

        return $this;
    }

    public function orHaving(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|string $condition): static {
        $this->having = $this->having
            ? $this->having->addOr($condition)
            : new ExpressionHaving($condition);

        return $this;
    }

    public function groupBy(string $field): static {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->groupBy = new ExpressionGroupBy($field);

        return $this;
    }

    public function addGroupBy(string $field): static {
        /** @noinspection PhpUnhandledExceptionInspection */
        $this->groupBy = $this->groupBy
            ? $this->groupBy->add($field)
            : new ExpressionGroupBy($field);

        return $this;
    }

    public function orderBy(string $field, string $direction): static {
        $this->orderBy = new ExpressionOrderBy($field, $direction);

        return $this;
    }

    public function addOrderBy(string $field, string $direction): static {
        $this->orderBy = $this->orderBy
            ? $this->orderBy->add($field, $direction)
            : new ExpressionOrderBy($field, $direction);

        return $this;
    }

    public function setVariable(string $name, mixed $value): static {
        $this->variables[$name] = $value;

        return $this;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function setVariables(array $values): static {
        $this->variables = array_merge(
            $this->variables,
            $values
        );

        return $this;
    }

    public function values(array $values): static {
        $this->values = $values;

        return $this;
    }

    public function addValue(array $value): static {
        $this->values[] = $value;

        return $this;
    }

    public function addValues(array $values): static {
        $this->values = array_merge(
            $this->values,
            $values
        );

        return $this;
    }

    public function setLimit(?int $limit, ?int $offset = null): static {
        $this->limit = $offset
            ? [$limit, $offset]
            : [$limit];

        return $this;
    }

    protected function compileSelect(): string {
        return $this->compileQueryParts(
            $this->getSelectQueryParts()
        );
    }

    protected function compileInsert(): string {
        return $this->compileQueryParts(
            $this->getInsertQueryParts()
        );
    }

    protected function compileUpdate(): string {
        return $this->compileQueryParts(
            $this->getUpdateQueryParts()
        );
    }

    protected function compileDelete(): string {
        return $this->compileQueryParts(
            $this->getDeleteQueryParts()
        );
    }

    protected function getSelectQueryParts(): array {
        return [
            "SELECT",
            implode(", ", $this->select),
            (string)$this->from,
            (string)$this->join,
            (string)$this->where,
            (string)$this->groupBy,
            (string)$this->having,
            (string)$this->orderBy,
            $this->getLimit()
        ];
    }

    protected function getInsertQueryParts(): array {
        $parts = [
            "INSERT INTO",
            $this->tableNameEscaper->escapeTableName($this->tableName),
        ];

        if ($this->set) {
            $parts[] = (string)$this->set;
        } else {
            $parts = [
                ...$parts,
                "VALUES",
                $this->compileRows()
            ];
        }

        return $parts;
    }

    protected function getUpdateQueryParts(): array {
        return [
            "UPDATE",
            $this->tableNameEscaper->escapeTableName($this->tableName),
            (string)$this->set,
            (string)$this->where
        ];
    }

    protected function getDeleteQueryParts(): array {
        return [
            "DELETE ",
            (string)$this->from,
            (string)$this->where
        ];
    }

    protected function getLimit(): string {
        return $this->limit
            ? "LIMIT " . implode(", ", $this->limit)
            : "";
    }

    protected function compileRows(): string {
        return "("
            . implode(
                "), (",
                array_map(
                    static fn(array $values): string => implode(", ", $values),
                    $this->values
                )
            )
            . ")";
    }

    protected function compileQueryParts(array $parts): string {
        return implode(
            " ",
            array_filter(
                $parts
            )
        );
    }

    public function clearSet(): static {
        $this->set = null;

        return $this;
    }

    public function clearFrom(): static {
        $this->from = null;

        return $this;
    }

    public function clearJoin(): static {
        $this->join = null;

        return $this;
    }

    public function clearWhere(): static {
        $this->where = null;

        return $this;
    }

    public function clearGroupBy(): static {
        $this->groupBy = null;

        return $this;
    }

    public function clearHaving(): static {
        $this->having = null;

        return $this;
    }

    public function clearOrderBy(): static {
        $this->orderBy = null;

        return $this;
    }

    public function clearLimit(): static {
        $this->limit = [];

        return $this;
    }

    public function clearVariables(): static {
        $this->variables = [];

        return $this;
    }

    public function clearValues(): static {
        $this->values = [];

        return $this;
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getResult(): array {
        $this->checkStorageDriver();

        return $this->dataStorageDriver->getResult(
            $this->getSQL(),
            $this->variables
        );
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getFirstResult(): array {
        $this->checkStorageDriver();

        return $this->dataStorageDriver->getFirstResult(
            $this->getSQL(),
            $this->variables
        );
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getFirstScalarResult(): mixed {
        $this->checkStorageDriver();

        return $this->dataStorageDriver->getFirstScalarResult(
            $this->getSQL(),
            $this->variables
        );
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function iterate(): iterable {
        $this->checkStorageDriver();

        return $this->dataStorageDriver->getResultsIterator(
            $this->getSQL(),
            $this->variables
        );
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function count(): int {
        $this->checkStorageDriver();

        $this->operation = QueryOperationEnum::SELECT;
        $select = $this->select;
        $this->select = ["COUNT(*)"];

        $result = (int)$this->getFirstScalarResult();

        $this->select = $select;

        return $result;
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function execute(): void {
        $this->checkStorageDriver();

        $this->dataStorageDriver->execute(
            $this->getSQL(),
            $this->variables
        );
    }

    /**
     * @throws QueryBuilderException
     */
    protected function checkStorageDriver(): void {
        if (!$this->dataStorageDriver) {
            throw new QueryBuilderException("Data storage driver is not set");
        }
    }

    public function getLastInsertId(): string {
        return $this->dataStorageDriver->getLastInsertId();
    }
}
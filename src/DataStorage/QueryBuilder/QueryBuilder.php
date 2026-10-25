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
use Runway\DataStorage\QueryBuilder\Expression\ExpressionIsNotNull;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionIsNull;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionJoin;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionMath;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionOrderBy;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionSet;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionWhere;
use Runway\DataStorage\QueryBuilder\ExpressionBuilder\IExpressionBuilder;
use Runway\Model\AEntity;
use Runway\Model\Exception\ModelException;
use Runway\Model\Helper\IDataStoragePropertiesHelper;
use Runway\Singleton\Container;

class QueryBuilder implements IQueryBuilder {
    protected QueryOperationEnum $operation = QueryOperationEnum::SELECT;

    /**
     * @var array<string|\Stringable> The columns (SQL or expressions).
     */
    protected array $select = ["*"];

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

    /**
     * @var array<array<string|int|float>> The rows of INSERT.
     */
    protected array $values = [];

    /**
     * @var string[]
     */
    protected array $columns = [];

    /**
     * @var array{0?: int, 1?: int}
     */
    protected array $limit = [];

    protected ?IDataStorageDriver $dataStorageDriver = null;

    /**
     * @var class-string<AEntity>|''
     */
    private string $entityFQN = '';

    /**
     * @param IDataStorageDriver|null $dataStorageDriver The driver of the container by default: it connects to the data
     *                                                  storage on the first query, not when the builder is created.
     */
    public function __construct(
        protected ITableNameEscaper            $tableNameEscaper,
        protected IExpressionBuilder           $expressionBuilder,
        protected IDataStoragePropertiesHelper $propHelper,
        ?IDataStorageDriver                    $dataStorageDriver = null
    ) {
        $this->dataStorageDriver = $dataStorageDriver;
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
     * @param string|\Stringable ...$args
     */
    public function select(string|\Stringable ...$args): static {
        $this->operation = QueryOperationEnum::SELECT;
        $this->select = $args ?: ["*"];

        return $this;
    }

    public function insert(): static {
        $this->operation = QueryOperationEnum::INSERT;

        return $this;
    }

    /**
     * @param string[]|null $columns
     */
    public function into(string $tableName, ?array $columns = null): static {
        $this->tableName = $tableName;

        if ($columns !== null) {
            $this->setColumns($columns);
        }

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

    public function from(string $tableNameOrEntityFQN, string $alias = '', string $indexBy = ''): static {
        if (is_a($tableNameOrEntityFQN, AEntity::class, true)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->setEntityFQN($tableNameOrEntityFQN);

            $tableNameOrEntityFQN = $this->propHelper->setModelFQN($tableNameOrEntityFQN)->getTableName();
        }

        $this->from = new ExpressionFrom(
            $tableNameOrEntityFQN,
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

    public function where(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static {
        $this->where = new ExpressionWhere($condition);

        return $this;
    }

    public function andWhere(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static {
        $this->where = $this->where
            ? $this->where->addAnd($condition)
            : new ExpressionWhere($condition);

        return $this;
    }

    public function orWhere(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static {
        $this->where = $this->where
            ? $this->where->addOr($condition)
            : new ExpressionWhere($condition);

        return $this;
    }

    public function having(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static {
        $this->having = new ExpressionHaving($condition);

        return $this;
    }

    public function andHaving(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static {
        $this->having = $this->having
            ? $this->having->addAnd($condition)
            : new ExpressionHaving($condition);

        return $this;
    }

    public function orHaving(ExpressionComparison|AExpressionBoolean|ExpressionIsNull|ExpressionIsNotNull|string $condition): static {
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

    /**
     * @param string[] $columns
     */
    public function columns(array $columns): static {
        $this->setColumns($columns);

        return $this;
    }

    public function addColumn(string $name): static {
        if (!in_array($name, $this->columns, true)) {
            $this->columns[] = $name;
        }

        return $this;
    }

    /**
     * @param string[] $columns
     */
    public function addColumns(array $columns): static {
        $this->setColumns(
            array_merge(
                $this->columns,
                $columns
            )
        );

        return $this;
    }

    /**
     * @param string[] $columns
     */
    protected function setColumns(array $columns): static {
        $this->columns = array_unique($columns);

        return $this;
    }

    /**
     * @param array<array<string|int|float>> $values
     */
    public function values(array $values): static {
        $this->values = $values;

        return $this;
    }

    /**
     * @param array<string|int|float> $value
     */
    public function addValue(array $value): static {
        $this->values[] = $value;

        return $this;
    }

    /**
     * @param array<array<string|int|float>> $values
     */
    public function addValues(array $values): static {
        $this->values = array_merge(
            $this->values,
            $values
        );

        return $this;
    }

    /**
     * "LIMIT $limit OFFSET $offset" (the portable syntax: "LIMIT a, b" means the offset first in MySQL).
     */
    public function setLimit(int $limit, ?int $offset = null): static {
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

    /**
     * @return array<string|\Stringable|null> See compileQueryParts().
     */
    protected function getSelectQueryParts(): array {
        return [
            "SELECT",
            implode(", ", $this->select),
            $this->from,
            $this->join,
            $this->where,
            $this->groupBy,
            $this->having,
            $this->orderBy,
            $this->getLimit()
        ];
    }

    /**
     * @return string[]
     */
    protected function getInsertQueryParts(): array {
        $parts = [
            "INSERT INTO",
            $this->tableNameEscaper->escapeTableName($this->tableName),
        ];

        if ($this->columns && $this->values) {
            $parts = [
                ...$parts,
                $this->compileColumns(),
                "VALUES",
                $this->compileRows()
            ];
        } elseif ($setParts = $this->set?->getParts()) {
            // "INSERT ... SET" is MySQL-only syntax, so SET parts are compiled to the portable
            // "(columns) VALUES (values)" form.
            $parts = [
                ...$parts,
                "(`" . implode("`, `", array_column($setParts, 0)) . "`)",
                "VALUES",
                "(" . implode(", ", array_map(static fn(array $part): string => (string)$part[1], $setParts)) . ")"
            ];
        }

        return $parts;
    }

    /**
     * @return array<string|\Stringable|null> See compileQueryParts().
     */
    protected function getUpdateQueryParts(): array {
        return [
            "UPDATE",
            $this->tableNameEscaper->escapeTableName($this->tableName),
            $this->set,
            $this->where
        ];
    }

    /**
     * @return array<string|\Stringable|null> See compileQueryParts().
     */
    protected function getDeleteQueryParts(): array {
        return [
            "DELETE ",
            $this->from,
            $this->where
        ];
    }

    protected function getLimit(): string {
        return $this->limit
            ? "LIMIT " . implode(" OFFSET ", $this->limit)
            : "";
    }

    protected function compileColumns(): string {
        return "(`"
               . implode("`, `", $this->columns)
               . "`)";
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

    /**
     * @param array<string|\Stringable|null> $parts The SQL of the parts (the expressions as strings), the empty ones are skipped.
     */
    protected function compileQueryParts(array $parts): string {
        return implode(
            " ",
            array_filter(
                array_map(static fn(string|\Stringable|null $part): string => (string)$part, $parts)
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
     * @return array<array<string, mixed>>
     *
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getResults(): array {
        return $this->getDataStorageDriver()->getResult(
            $this->getSQL(),
            $this->variables
        );
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     * @throws ModelException
     */
    public function getEntities(): array
    {
        if (!$this->entityFQN) {
            throw new QueryBuilderException("Entity type is not set");
        }

        return array_map(
            fn(array $row): AEntity => $this->mapRow($row),
            $this->getResults()
        );
    }

    /**
     * @return iterable<AEntity>
     *
     * @throws ModelException
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function iterateEntities(): iterable
    {
        if (!$this->entityFQN) {
            throw new QueryBuilderException("Entity type is not set");
        }

        $result = $this->iterate();

        foreach ($result as $row) {
            yield $this->mapRow($row);
        }
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     * @throws ModelException
     */
    public function getFirstEntity(): ?AEntity
    {
        $firstRow = $this->getFirstResult();
        return $firstRow
            ? $this->mapRow($firstRow)
            : null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws ModelException
     */
    private function mapRow(array $row): AEntity {
        /** @var AEntity $entity */
        $entity = new $this->entityFQN();
        return $entity->map($row);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getFirstResult(): array {
        return $this->getDataStorageDriver()->getFirstResult(
            $this->getSQL(),
            $this->variables
        );
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function getFirstScalarResult(): mixed {
        return $this->getDataStorageDriver()->getFirstScalarResult(
            $this->getSQL(),
            $this->variables
        );
    }

    /**
     * @return iterable<array<string, mixed>>
     *
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function iterate(): iterable {
        return $this->getDataStorageDriver()->getResultsIterator(
            $this->getSQL(),
            $this->variables
        );
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function count(): int {
        // All the matching rows: without the limit and the order (the count of a page would be 0 from the second page).
        $query = clone $this;
        $query->operation = QueryOperationEnum::SELECT;
        $query->select = ["COUNT(*)"];
        $query->limit = [];
        $query->orderBy = null;

        $count = $query->getFirstScalarResult();

        return is_numeric($count) ? (int)$count : 0;
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function execute(): void {
        $this->getDataStorageDriver()->execute(
            $this->getSQL(),
            $this->variables
        );
    }

    /**
     * @throws DBConnectionException
     */
    protected function getDataStorageDriver(): IDataStorageDriver {
        return $this->dataStorageDriver ??= Container::getInstance()->getDataStorageDriver();
    }

    public function getLastInsertId(): string {
        return $this->getDataStorageDriver()->getLastInsertId();
    }

    /**
     * @param string $entityFQN An AEntity subclass, '' for none.
     *
     * @throws QueryBuilderException
     */
    public function setEntityFQN(string $entityFQN): static {
        if ($entityFQN !== "") {
            if (!class_exists($entityFQN)) {
                throw new QueryBuilderException("Entity '$entityFQN' does not exist");
            }

            if (!is_a($entityFQN, AEntity::class, true)) {
                throw new QueryBuilderException("Entity '$entityFQN' does not implement " . AEntity::class);
            }
        }

        $this->entityFQN = $entityFQN;

        return $this;
    }

    /**
     * @return class-string<AEntity>|''
     */
    public function getEntityFQN(): string {
        return $this->entityFQN;
    }
}
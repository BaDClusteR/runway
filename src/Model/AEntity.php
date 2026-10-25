<?php

declare(strict_types=1);

namespace Runway\Model;

use DateMalformedStringException;
use DateTime;
use Runway\DataStorage\Exception\DBConnectionException;
use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
use Runway\DataStorage\QueryBuilder\IQueryBuilder;
use Runway\Exception\RuntimeException;
use Runway\Model\Converter\IDataStoragePropertiesConverter;
use Runway\Model\DTO\DataStoragePropertyDTO;
use Runway\Model\DTO\DataStorageReferenceDTO;
use Runway\Model\Exception\EntityNotFoundException;
use Runway\Model\Exception\ModelException;
use Runway\Model\Helper\IDataStoragePropertiesHelper;
use Runway\Singleton\Container;
use Runway\Singleton\IConverter;

/**
 * The subclasses keep the constructor of an entity (an optional id): the search methods create them with "new static()".
 *
 * @phpstan-consistent-constructor
 */
abstract class AEntity {
    /**
     * The query variable of the id in UPDATE and DELETE: the other variables of UPDATE are the column names.
     */
    protected const string ID_VARIABLE = '__runway_id';

    protected bool $__isChanged = false;

    protected bool $__isPersisting = false;

    /**
     * @var array<string, mixed>|null Column => the value in the data storage (loaded or saved), see isStoredValuesChanged().
     */
    protected ?array $__storedValues = null;

    /** @var array<class-string<AEntity>, IDataStoragePropertiesHelper> */
    protected static array $propHelpers = [];

    protected static ?IDataStoragePropertiesConverter $propConverter = null;

    /**
     * @throws EntityNotFoundException
     * @throws DBConnectionException
     * @throws ModelException
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function __construct(?int $id = null) {
        $this->init();

        if (!static::getPropHelper()->getPrimaryProp()) {
            throw new ModelException(
                sprintf(
                    "%s does not have a primary property.",
                    static::class
                )
            );
        }

        if ($id !== null) {
            if ($row = $this->findRowById($id)) {
                $this->map($row);
            } else {
                throw new EntityNotFoundException(static::class, $id);
            }
        }
    }

    protected function init(): void {
        static::getPropConverter();
    }

    protected static function getPropConverter(): IDataStoragePropertiesConverter {
        return static::$propConverter ??= Container::getInstance()->getService(IDataStoragePropertiesConverter::class);
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    private function findRowById(int $id): ?array {
        $qb = static::getQueryBuilder();

        $dsRow = $qb->where(
            $qb->expr()->eq(
                $this->getPrimaryProp()->getColumn(),
                ":id"
            )
        )
        ->setVariable("id", $id)
        ->getFirstResult();

        return $dsRow ?: null;
    }

    /**
     * @throws ModelException
     */
    protected function getPrimaryProp(): DataStoragePropertyDTO {
        return static::getPropHelper()->getPrimaryProp()
            ?? throw new ModelException(sprintf("%s does not have a primary property.", static::class));
    }

    protected function getTable(): string {
        return static::getPropHelper()->getTableName();
    }

    /**
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    public function getUniqueIdentifier(): mixed {
        return $this->getProp(
            $this->getPrimaryProp()->getPropName()
        );
    }

    /**
     * Saves the model to the data storage. Persistent entity will be updated, non-persistent - inserted.
     *
     * @throws QueryBuilderException
     * @throws DBException
     * @throws ModelException
     * @throws RuntimeException A new entity refers to a new entity that refers to it (neither can be saved first).
     */
    public function persist(): void {
        // A circular reference (A refers to B, B refers to A, neither is saved yet): the inner persist() of A returns,
        // and the converter of B sees A is still not persistent (see DataStoragePropertiesConverter::convert()).
        if ($this->__isPersisting) {
            return;
        }

        $this->__isPersisting = true;

        try {
            $this->doPersist();
        } finally {
            $this->__isPersisting = false;
        }
    }

    /**
     * @throws QueryBuilderException
     * @throws DBException
     * @throws ModelException
     * @throws RuntimeException
     */
    protected function doPersist(): void {
        $qb = static::getPureQueryBuilder();
        $values = [];

        foreach ($this->getProps() as $prop) {
            // we shouldn't update entity's id on update, should we?
            if (!$prop->isPrimary()) {
                $column = $prop->getColumn();
                $values[$column] = static::getPropConverter()->convert(
                    $prop->getPropType(),
                    $prop->getDataStorageType(),
                    $this->getStoredPropValue($prop)
                );

                $qb->addSet($column, ":$column")
                   ->setVariable($column, $values[$column]);
            }
        }

        // The model has an id? Then just update in the data storage.
        if ($this->isPersistent()) {
            // Persistent model did not change? Do nothing.
            if ($this->__isChanged || $this->isStoredValuesChanged($values)) {
                // The id is a query variable: a string id (e.g. a code) is not a part of the SQL.
                $qb->update(static::getPropHelper()->getTableName())
                   ->where(
                       $qb->expr()->eq(
                           $this->getPrimaryProp()->getColumn(),
                           ":" . static::ID_VARIABLE
                       )
                   )
                   ->setVariable(static::ID_VARIABLE, $this->getUniqueIdentifier())
                   ->execute();
            }

            // Otherwise, add it to the data storage and update the id prop.
        } else {
            $qb->insert()
               ->into(static::getPropHelper()->getTableName())
               ->execute();

            $this->setProp(
                $this->getPrimaryProp()->getPropName(),
                (int)$qb->getLastInsertId()
            );
        }

        // Saved: the next persist() does not update it again unless it changes.
        $this->__isChanged = false;
        $this->__storedValues = $values;

        $this->persistReferences();
    }

    /**
     * A change made by a setter of the entity itself (not by setProp()) is found by the values: they are compared to
     * the loaded or the last saved ones. Unknown ones (neither loaded nor saved) are not a change.
     *
     * @param array<string, mixed> $values Column => value to save.
     */
    protected function isStoredValuesChanged(array $values): bool {
        if ($this->__storedValues === null) {
            return false;
        }

        foreach ($values as $column => $value) {
            $storedValue = $this->__storedValues[$column] ?? null;

            // A driver can give a number as a string ("4.5" of a DECIMAL column).
            $isSame = ($storedValue === $value)
                      || (is_scalar($storedValue) && is_scalar($value) && (string)$storedValue === (string)$value);

            if (!$isSame) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws ModelException
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function isPersistent(): bool {
        return !empty($this->getUniqueIdentifier());
    }

    /**
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    private function persistReferences(): void {
        foreach ($this->getReferences() as $ref) {
            // The loaded or the given ones only: the references in the data storage are saved already.
            foreach ((array)($this->{$ref->propName} ?? []) as $propValue) {
                if ($propValue instanceof self) {
                    $propValue->persist();
                }
            }
        }
    }

    /**
     * @return AEntity[]
     *
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    private function getRefModelEntity(string $propName): array {
        // A non-persistent entity cannot have referenced entities in the data storage yet. Moreover, searching by it
        // would persist it to get its id, which can fail if its required props are not set yet.
        if (!$this->isPersistent()) {
            return [];
        }

        $refProp = static::getPropHelper()->getRefByPropName($propName);
        $refModel = $refProp?->refModel;

        if ($refModel !== null && is_a($refModel, self::class, true)) {
            return $refModel::find(
                [$refProp->refProp => $this],
                $refProp->refOrderBy ?: null
            );
        }

        return [];
    }

    /**
     * @throws ModelException
     * @throws DBException
     * @throws QueryBuilderException
     */
    protected function getProp(string $propName): mixed {
        $getter = "get" . $this->getConverter()->capitalize($propName);
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }

        if (static::getPropHelper()->getPropByName($propName)?->isDefaultGetter()) {
            return $this->$propName ?? null;
        }

        if (static::getPropHelper()->getRefByPropName($propName)?->isDefaultGetter) {
            $this->{$propName} ??= $this->getRefModelEntity($propName);

            return $this->{$propName};
        }

        return null;
    }

    /**
     * The value to save: of the getter of the entity, or of the prop itself. A prop without the default getter
     * (NoGetter, e.g. a password hash) is hidden from toArray() and get*(), but it is saved.
     *
     * @throws ModelException
     * @throws DBException
     * @throws QueryBuilderException
     */
    protected function getStoredPropValue(DataStoragePropertyDTO $prop): mixed {
        $propName = $prop->getPropName();

        return method_exists($this, "get" . $this->getConverter()->capitalize($propName))
            ? $this->getProp($propName)
            : ($this->$propName ?? null);
    }

    /**
     * Handles only props, do not update refs.
     *
     * @throws QueryBuilderException
     * @throws ModelException
     * @throws DBException
     */
    protected function setProp(string $propName, mixed $value): static {
        if (
            ($value !== $this->getProp($propName))
            && ($prop = static::getPropHelper()->getPropByName($propName))
        ) {
            $method = "set" . $this->getConverter()->capitalize($propName);

            if (method_exists($this, $method)) {
                $this->$method(
                    $this->convertPropValue($prop, $value)
                );
                $this->__isChanged = true;
            } elseif ($prop->isDefaultSetter()) {
                $this->$propName = $this->convertPropValue($prop, $value);
                $this->__isChanged = true;
            }
        }

        return $this;
    }

    protected function convertPropValue(DataStoragePropertyDTO $prop, mixed $propValue): mixed {
        if (
            (
                is_string($propValue)
                || is_numeric($propValue)
            )
            && $prop->getPropType() === DateTime::class
        ) {
            try {
                return new DateTime(
                    (is_numeric($propValue) ? "@" : "")
                    . $propValue
                );
            } catch (DateMalformedStringException) {
                // As is: the setter or the prop type rejects it.
            }
        }

        return $propValue;
    }

    /**
     * @param array<string, mixed> $row A row of the data storage: column => value.
     *
     * @throws ModelException
     */
    public function map(array $row): static {
        foreach ($row as $column => $value) {
            if ($prop = static::getPropHelper()->getPropByColumnName($column)) {
                $this->__storedValues[$column] = $value;
                $this->{$prop->getPropName()} = static::getPropConverter()->convert(
                    $prop->getDataStorageType(),
                    $prop->getPropType(),
                    $value
                );
            }
        }

        return $this;
    }

    /**
     * @throws DBException
     * @throws QueryBuilderException
     * @throws ModelException
     *
     * @param array<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed {
        if (str_starts_with($name, "get")) {
            return $this->getProp(
                $this->getConverter()->deCapitalize(
                    substr($name, 3)
                )
            );
        }

        if (str_starts_with($name, "set")) {
            return $this->setProp(
                $this->getConverter()->deCapitalize(
                    substr($name, 3)
                ),
                ($arguments[0] ?? null)
            );
        }

        return null;
    }

    /**
     * @return array<string, mixed> Prop name => value (the entities as arrays, the dates as strings).
     *
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    public function toArray(bool $includeUniqueIdentifier = false): array {
        $result = [];

        foreach ($this->getProps() as $prop) {
            if ($includeUniqueIdentifier || !$prop->isPrimary()) {
                $propName = $prop->getPropName();

                $result[$propName] = $this->convertPropToArray(
                    $propName,
                    $this->getProp($propName)
                );
            }
        }

        return $result;
    }

    /**
     * @throws QueryBuilderException
     * @throws DBException
     * @throws ModelException
     */
    protected function convertPropToArray(string $propName, mixed $value): mixed {
        $convertMethod = "toArray" . $this->getConverter()->capitalize($propName);

        if (method_exists($this, $convertMethod)) {
            return $this->{$convertMethod}($value);
        }

        if ($value instanceof self) {
            return $value->toArray();
        }

        if ($value instanceof DateTime) {
            return $this->getConverter()->dateTimeToString($value);
        }

        return $value;
    }

    /**
     * @param string[] $fields
     *
     * @return array<string, mixed>
     *
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    public function get(array $fields): array {
        $result = [];

        foreach ($fields as $field) {
            if (static::getPropHelper()->isPropExists($field)) {
                $result[$field] = $this->getProp($field);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $props
     *
     * @throws ModelException
     * @throws QueryBuilderException
     * @throws DBException
     */
    public function set(array $props): void {
        foreach ($props as $propName => $value) {
            if (static::getPropHelper()->isPropExists($propName)) {
                $this->setProp($propName, $value);
            }
        }
    }

    /**
     * @param array<string, mixed>     $conditions Prop name => value: null is "IS NULL", an array is "IN".
     * @param array<int, string>|string|null $orderBy    The prop name or [prop name, "ASC" or "DESC"].
     * @param array<int, int>|int|null       $limit      The limit or [limit, offset].
     *
     * @return static[]
     *
     * @throws ModelException
     * @throws DBException
     * @throws QueryBuilderException
     */
    public static function find(
        array $conditions = [],
        array|string|null $orderBy = null,
        array|int|null $limit = null
    ): array {
        $result = [];
        $qb = static::generateSearchQueryBuilder($conditions, $orderBy, $limit);

        foreach ($qb->getResults() as $row) {
            $result[] = new static()->map($row);
        }

        return $result;
    }

    /**
     * @param array<string, mixed>     $conditions Prop name => value: null is "IS NULL", an array is "IN".
     * @param array<int, string>|string|null $orderBy    The prop name or [prop name, "ASC" or "DESC"].
     * @param array<int, int>|int|null       $limit      The limit or [limit, offset].
     *
     * @return iterable<static>
     *
     * @throws ModelException
     * @throws DBException
     * @throws QueryBuilderException
     */
    public static function iterate(
        array $conditions = [],
        array|string|null $orderBy = null,
        array|int|null $limit = null
    ): iterable {
        $qb = static::generateSearchQueryBuilder($conditions, $orderBy, $limit);

        foreach ($qb->iterate() as $row) {
            yield new static()->map($row);
        }
    }

    /**
     * @param array<string, mixed>     $conditions Prop name => value: null is "IS NULL", an array is "IN".
     * @param array<int, string>|string|null $orderBy    The prop name or [prop name, "ASC" or "DESC"].
     *
     * @throws QueryBuilderException
     * @throws ModelException
     * @throws DBException
     */
    public static function findOne(array $conditions = [], array|string|null $orderBy = null): ?static {
        $qb = static::generateSearchQueryBuilder($conditions, $orderBy);

        if ($row = $qb->getFirstResult()) {
            return new static()->map($row);
        }

        return null;
    }

    /**
     * @param array<int, string>|string|null $orderBy See find().
     *
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    public static function findByUniqueIdentifier(int|string|null $id, array|string|null $orderBy = null): ?static {
        if (!$id) {
            return null;
        }

        return static::findOne(
            [(string)static::getPropHelper()->getPrimaryProp()?->getPropName() => $id],
            $orderBy
        );
    }

    /**
     * @param array<string, mixed>     $conditions Prop name => value: null is "IS NULL", an array is "IN".
     * @param array<int, string>|string|null $orderBy    The prop name or [prop name, "ASC" or "DESC"].
     * @param array<int, int>|int|null       $limit      The limit or [limit, offset].
     *
     * @throws ModelException
     */
    protected static function generateSearchQueryBuilder(
        array             $conditions,
        array|string|null $orderBy = null,
        array|int|null    $limit = null
    ): IQueryBuilder {
        // Should be the first line of the method, triggers initialization of helpers/converters/etc.
        new static();

        $qb = static::getQueryBuilder();

        foreach ($conditions as $propName => $value) {
            if ($prop = static::getPropHelper()->getPropByName($propName)) {
                if ($value === null) {
                    $qb->andWhere(
                        $qb->expr()->isNull(
                            $prop->getColumn()
                        )
                    );
                } elseif (is_array($value)) {
                    if (empty($value)) {
                        $qb->andWhere("1 = 0");
                    } else {
                        // Every value is a query variable, like in the "=" condition below.
                        $placeholders = [];

                        foreach (array_values($value) as $index => $item) {
                            $placeholders[] = ":{$propName}_$index";
                            $qb->setVariable(
                                "{$propName}_$index",
                                static::getPropConverter()->convert(
                                    $prop->getPropType(),
                                    $prop->getDataStorageType(),
                                    $item
                                )
                            );
                        }

                        $qb->andWhere(
                            $qb->expr()->in(
                                $prop->getColumn(),
                                $placeholders
                            )
                        );
                    }
                } else {
                    $qb->andWhere(
                        $qb->expr()->eq(
                            $prop->getColumn(),
                            ":{$propName}"
                        )
                    )->setVariable(
                        $propName,
                        static::getPropConverter()->convert(
                            $prop->getPropType(),
                            $prop->getDataStorageType(),
                            $value
                        )
                    );
                }
            }
        }

        if ($orderBy) {
            [$orderByProp, $direction] = is_array($orderBy)
                ? [$orderBy[0] ?? "", $orderBy[1] ?? "ASC"]
                : [$orderBy, "ASC"];

            // A prop only: the column is not a part of the request.
            if ($prop = static::getPropHelper()->getPropByName($orderByProp)) {
                $qb->addOrderBy(
                    $prop->getColumn(),
                    $direction
                );
            }
        }

        if ($limit) {
            if (is_array($limit)) {
                $qb->setLimit($limit[0] ?? 0, $limit[1] ?? null);
            } else {
                $qb->setLimit($limit);
            }
        }

        return $qb;
    }

    /**
     * @throws ModelException
     * @throws DBException
     * @throws QueryBuilderException
     */
    public function remove(): void {
        $qb = static::getQueryBuilder();

        $qb->delete()
           ->where(
               $qb->expr()->eq(
                   $this->getPrimaryProp()->getColumn(),
                   ":" . static::ID_VARIABLE
               )
           )
           ->setVariable(static::ID_VARIABLE, $this->getUniqueIdentifier())
           ->execute();
    }

    /**
     * @return DataStoragePropertyDTO[]
     *
     * @throws ModelException
     */
    protected function getProps(): array {
        return static::getPropHelper()->getProps();
    }

    /**
     * @return DataStorageReferenceDTO[]
     *
     * @throws ModelException
     */
    protected function getReferences(): array {
        return static::getPropHelper()->getReferences();
    }

    protected function getConverter(): IConverter {
        return Container::getInstance()->getService(IConverter::class);
    }

    public static function getPureQueryBuilder(): IQueryBuilder {
        return Container::getInstance()->getService(IQueryBuilder::class);
    }

    public static function getQueryBuilder(string $alias = ''): IQueryBuilder {
        return static::getPureQueryBuilder()
            ->select()
            ->from(static::class, $alias);
    }

    protected static function getPropHelper(): IDataStoragePropertiesHelper {
        if (empty(static::$propHelpers[static::class])) {
            static::$propHelpers[static::class] = Container::getInstance()->getService(IDataStoragePropertiesHelper::class);
            static::$propHelpers[static::class]->setModelFQN(static::class);
        }

        return static::$propHelpers[static::class];
    }
}
<?php

declare(strict_types=1);

namespace Runway\Model;

use DateMalformedStringException;
use DateTime;
use Runway\DataStorage\Exception\DBConnectionException;
use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
use Runway\DataStorage\QueryBuilder\IQueryBuilder;
use Runway\Model\Converter\IDataStoragePropertiesConverter;
use Runway\Model\DTO\DataStoragePropertyDTO;
use Runway\Model\DTO\DataStorageReferenceDTO;
use Runway\Model\Exception\EntityNotFoundException;
use Runway\Model\Exception\ModelException;
use Runway\Model\Helper\IDataStoragePropertiesHelper;
use Runway\Singleton\Container;
use Runway\Singleton\IConverter;

abstract class AEntity {
    private bool $__isChanged = false;

    private bool $__isPersisting = false;

    /** @var IDataStoragePropertiesHelper[] */
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
        $container = Container::getInstance();

        static::$propConverter ??= $container->getService(IDataStoragePropertiesConverter::class);
    }

    /**
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    private function findRowById(int $id): ?array {
        $qb = static::getQueryBuilder();

        $dsRow = $qb->select()
            ->from(static::getPropHelper()->getTableName())
            ->where(
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
        return static::getPropHelper()->getPrimaryProp();
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
     */
    public function persist(): void {
        if ($this->__isPersisting) {
            return;
        }

        $qb = static::getQueryBuilder();

        foreach ($this->getProps() as $prop) {
            // we shouldn't update entity's id on update, should we?
            if (!$prop->isPrimary()) {
                $column = $prop->getColumn();

                $qb->addSet(
                    $column,
                    ":$column"
                )->setVariable(
                    $column,
                    static::$propConverter->convert(
                        $prop->getPropType(),
                        $prop->getDataStorageType(),
                        $this->getProp(
                            $prop->getPropName()
                        )
                    )
                );
            }
        }

        // The model has an id? Then just update in the data storage.
        if ($this->isPersistent()) {
            // Persistent model did not change? Do nothing.
            if ($this->__isChanged) {
                $qb->update(static::getPropHelper()->getTableName())
                   ->where(
                       $qb->expr()->eq(
                           $this->getPrimaryProp()->getColumn(),
                           $this->getUniqueIdentifier()
                       )
                   )
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

        $this->persistReferences();
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
            $propValues = $this->getProp($ref->propName);

            if ($propValues !== null) {

                /** @var AEntity $propValue */
                foreach ((array)$propValues as $propValue) {
                    $propValue->persist();
                }
            }
        }
    }

    /**
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    private function getRefModelEntity(string $propName): array {
        if ($refProp = static::getPropHelper()->getRefByPropName($propName)) {
            return call_user_func(
                [$refProp->refModel, 'find'],
                [$refProp->refProp => $this],
                $refProp->refOrderBy
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
                return $propValue;
            }
        }

        return $propValue;
    }

    /**
     * @throws ModelException
     */
    protected function map(array $row): static {
        foreach ($row as $column => $value) {
            if ($prop = static::getPropHelper()->getPropByColumnName($column)) {
                $this->{$prop->getPropName()} = static::$propConverter->convert(
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
     */
    public function __call(string $name, array $arguments) {
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
     * @param array             $conditions
     * @param array|string|null $orderBy
     *
     * @return static[]
     *
     * @throws ModelException
     * @throws DBException
     * @throws QueryBuilderException
     */
    public static function find(array $conditions = [], array|string|null $orderBy = null): array {
        $result = [];
        $qb = static::generateSearchQueryBuilder($conditions, $orderBy);

        foreach ($qb->getResult() as $row) {
            $result[] = new static()->map($row);
        }

        return $result;
    }

    /**
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
     * @throws DBException
     * @throws ModelException
     * @throws QueryBuilderException
     */
    public static function findByUniqueIdentifier(int|string $id, array|string|null $orderBy = null): ?static {
        return static::findOne(
            [(string)static::getPropHelper()->getPrimaryProp()?->getPropName() => $id],
            $orderBy
        );
    }

    /**
     * @param array<string, mixed>                    $conditions
     * @param array{0: string, 1: string}|string|null $orderBy
     * @param array{0: int, 1: int}|int|null          $limit
     *
     * @throws ModelException
     */
    protected static function generateSearchQueryBuilder(
        array             $conditions,
        array|string|null $orderBy = null,
        array|int|null    $limit = null
    ): IQueryBuilder {
        // Should be the first line of the method, triggers initialization of helpers/converters/etc.
        $instance = new static();

        $qb = (static::getQueryBuilder())
            ->select()
            ->from($instance->getTable());

        foreach ($conditions as $propName => $value) {
            if ($prop = static::getPropHelper()->getPropByName($propName)) {
                if ($value === null) {
                    $qb->andWhere(
                        $qb->expr()->isNull(
                            $prop->getColumn()
                        )
                    );
                } elseif (is_array($value)) {
                    $qb->andWhere(
                        $qb->expr()->in(
                            $prop->getColumn(),
                            $value
                        )
                    );
                } else {
                    $qb->andWhere(
                        $qb->expr()->eq(
                            $prop->getColumn(),
                            ":{$propName}"
                        )
                    )->setVariable(
                        $propName,
                        static::$propConverter->convert(
                            $prop->getPropType(),
                            $prop->getDataStorageType(),
                            $value
                        )
                    );
                }
            }
        }

        if ($orderBy) {
            if (!is_array($orderBy)) {
                $orderBy = [$orderBy, "ASC"];
            }

            if ($prop = static::getPropHelper()->getPropByName($orderBy[0])) {
                $qb->addOrderBy(
                    $prop->getColumn(),
                    $orderBy[1]
                );
            }
        }

        if ($limit) {
            if (is_array($limit)) {
                $qb->setLimit($limit[0], $limit[1]);
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
           ->from($this->getTable())
           ->where(
               $qb->expr()->eq(
                   $this->getPrimaryProp()->getColumn(),
                   $this->getProp(
                       $this->getPrimaryProp()->getPropName()
                   )
               )
           )->execute();
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

    protected static function getQueryBuilder(): IQueryBuilder {
        return Container::getInstance()->getService(IQueryBuilder::class);
    }

    protected static function getPropHelper(): IDataStoragePropertiesHelper {
        if (empty(static::$propHelpers[static::class])) {
            static::$propHelpers[static::class] = Container::getInstance()->getService(IDataStoragePropertiesHelper::class);
            static::$propHelpers[static::class]->setModelFQN(static::class);
        }

        return static::$propHelpers[static::class];
    }
}
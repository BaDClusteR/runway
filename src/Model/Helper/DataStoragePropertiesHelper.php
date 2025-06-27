<?php

declare(strict_types=1);

namespace Runway\Model\Helper;

use Runway\Model\DTO\DataStoragePropertyDTO;
use Runway\Model\DTO\DataStorageReferenceDTO;
use Runway\Model\Exception\ModelException;
use Runway\Model\Provider\IDataStoragePropertiesProvider;

class DataStoragePropertiesHelper implements IDataStoragePropertiesHelper {
    /**
     * @var DataStoragePropertyDTO[]|null
     */
    protected ?array $props = null;

    protected string $modelFQN = '';

    /**
     * @var DataStorageReferenceDTO[]|null
     */
    protected ?array $refs = null;

    protected DataStoragePropertyDTO|null|bool $primaryProp = false;

    protected ?string $tableName = null;

    public function __construct(
        protected IDataStoragePropertiesProvider $propertiesProvider
    ) {}

    /**
     * @throws ModelException
     */
    public function getPropNameByColumnName(string $columnName): string {
        foreach ($this->getProps() as $prop) {
            if ($prop->getColumn() === $columnName) {
                return $prop->getPropName();
            }
        }

        return "";
    }

    /**
     * @throws ModelException
     */
    public function getColumnNameByPropName(string $propName): string {
        foreach ($this->getProps() as $prop) {
            if ($prop->getPropName() === $propName) {
                return $prop->getColumn();
            }
        }

        return "";
    }

    public function setModelFQN(string $modelFQN): static {
        if ($this->modelFQN !== $modelFQN) {
            $this->modelFQN = $modelFQN;
            $this->props = null;
            $this->refs = null;
            $this->primaryProp = false;
        }

        return $this;
    }

    public function getModelFQN(): string {
        return $this->modelFQN;
    }

    /**
     * @return DataStoragePropertyDTO[]
     *
     * @throws ModelException
     */
    public function getProps(): array {
        if ($this->props === null) {
            $this->defineProps();
        }

        return $this->props;
    }

    /**
     * @throws ModelException
     */
    protected function defineProps(): void {
        $this->props = $this->propertiesProvider->getDataStorageProperties(
            $this->modelFQN
        );
    }

    /**
     * @return string[]
     * @throws ModelException
     */
    public function getPropNames(): array {
        return array_map(
            static fn(DataStoragePropertyDTO $prop): string => $prop->getPropName(),
            $this->getProps()
        );
    }

    /**
     * @throws ModelException
     */
    public function getPropByName(string $propName): ?DataStoragePropertyDTO {
        return array_find(
            $this->getProps(),
            static fn(DataStoragePropertyDTO $prop) => ($prop->getPropName() === $propName)
        );
    }

    /**
     * @throws ModelException
     */
    public function getPropByColumnName(string $columnName): ?DataStoragePropertyDTO {
        return array_find(
            $this->getProps(),
            static fn(DataStoragePropertyDTO $prop) => ($prop->getColumn() === $columnName)
        );
    }

    /**
     * @return DataStorageReferenceDTO[]
     *
     * @throws ModelException
     */
    public function getReferences(): array {
        if ($this->refs === null) {
            $this->defineReferences();
        }

        return $this->refs;
    }

    /**
     * @throws ModelException
     */
    protected function defineReferences(): void {
        $this->refs = $this->propertiesProvider->getDataStorageReferences(
            $this->modelFQN
        );
    }

    /**
     * @return DataStoragePropertyDTO|null If several props are marked as primary, method will return the first one.
     *
     * @throws ModelException
     */
    public function getPrimaryProp(): ?DataStoragePropertyDTO {
        if ($this->primaryProp === false) {
            $this->primaryProp = array_find(
                $this->getProps(),
                static fn(DataStoragePropertyDTO $prop): bool => $prop->isPrimary()
            );
        }

        return $this->primaryProp;
    }

    /**
     * @throws ModelException
     */
    public function getTableName(): string {
        if ($this->tableName === null) {
            $this->tableName = $this->propertiesProvider->getTableName($this->modelFQN);
        }

        return $this->tableName;
    }

    /**
     * @throws ModelException
     */
    public function getRefByPropName(string $propName): ?DataStorageReferenceDTO {
        return array_find(
            $this->getReferences(),
            static fn(DataStorageReferenceDTO $ref): bool => $ref->propName === $propName
        );
    }

    /**
     * @throws ModelException
     */
    public function isPropExists(string $propName): bool {
        return $this->getPropByName($propName) !== null;
    }
}
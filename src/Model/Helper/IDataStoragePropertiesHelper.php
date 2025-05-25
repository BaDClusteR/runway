<?php

namespace Runway\Model\Helper;

use Runway\Model\DTO\DataStoragePropertyDTO;
use Runway\Model\DTO\DataStorageReferenceDTO;
use Runway\Model\Exception\ModelException;

interface IDataStoragePropertiesHelper {
    public function setModelFQN(string $modelFQN): static;

    public function getModelFQN(): string;

    /**
     * @throws ModelException
     */
    public function getPropNameByColumnName(string $columnName): string;

    /**
     * @throws ModelException
     */
    public function getColumnNameByPropName(string $propName): string;

    /**
     * @return DataStoragePropertyDTO[]
     *
     * @throws ModelException
     */
    public function getProps(): array;

    /**
     * @return string[]
     *
     * @throws ModelException
     */
    public function getPropNames(): array;

    /**
     * @throws ModelException
     */
    public function isPropExists(string $propName): bool;

    /**
     * @throws ModelException
     */
    public function getPropByName(string $propName): ?DataStoragePropertyDTO;

    /**
     * @throws ModelException
     */
    public function getPropByColumnName(string $columnName): ?DataStoragePropertyDTO;

    /**
     * @return DataStoragePropertyDTO|null If several props are marked as primary, method will return the first one.
     *
     * @throws ModelException
     */
    public function getPrimaryProp(): ?DataStoragePropertyDTO;

    /**
     * @return DataStoragePropertyDTO[]
     *
     * @throws ModelException
     */
    public function getReferences(): array;

    /**
     * @throws ModelException
     */
    public function getRefByPropName(string $propName): ?DataStorageReferenceDTO;

    public function getTableName(): string;
}
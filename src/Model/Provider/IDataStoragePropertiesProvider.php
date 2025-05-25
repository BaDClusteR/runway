<?php

namespace Runway\Model\Provider;

use Runway\Model\DTO\DataStoragePropertyDTO;
use Runway\Model\DTO\DataStorageReferenceDTO;
use Runway\Model\Exception\ModelException;

interface IDataStoragePropertiesProvider {
    /**
     * @return DataStoragePropertyDTO[]
     * @throws ModelException
     */
    public function getDataStorageProperties(string $modelFQN): array;

    /**
     * @return DataStorageReferenceDTO[]
     *
     * @throws ModelException
     */
    public function getDataStorageReferences(string $modelFQN): array;

    /**
     * @throws ModelException
     */
    public function getTableName(string $modelFQN): string;
}
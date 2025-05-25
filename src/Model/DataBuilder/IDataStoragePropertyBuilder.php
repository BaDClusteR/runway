<?php

namespace Runway\Model\DataBuilder;

use Runway\Model\DTO\DataStoragePropertyDTO;
use Runway\Model\DTO\DataStorageReferenceDTO;
use ReflectionProperty;

interface IDataStoragePropertyBuilder {
    public function buildDataStorageProperty(ReflectionProperty $property): ?DataStoragePropertyDTO;

    public function buildDataStorageReference(ReflectionProperty $property): ?DataStorageReferenceDTO;

    public function buildTableName(ReflectionProperty $property): ?string;
}
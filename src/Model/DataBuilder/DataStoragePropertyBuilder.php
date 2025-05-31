<?php

declare(strict_types=1);

namespace Runway\Model\DataBuilder;

use Runway\DataStorage\Attribute\Column;
use Runway\DataStorage\Attribute\Id;
use Runway\DataStorage\Attribute\NoGetter;
use Runway\DataStorage\Attribute\NoSetter;
use Runway\DataStorage\Attribute\Reference;
use Runway\DataStorage\Attribute\Table;
use Runway\Model\AEntity;
use Runway\Model\DTO\DataStoragePropertyDTO;
use Runway\Model\DTO\DataStorageReferenceDTO;
use ReflectionAttribute;
use ReflectionProperty;

class DataStoragePropertyBuilder implements IDataStoragePropertyBuilder {
    public function buildDataStorageProperty(ReflectionProperty $property): ?DataStoragePropertyDTO {
        $propType = ($property->getType()?->getName() ?? "");

        $result = new DataStoragePropertyDTO(
            propType: $propType,
            dataStorageType: $this->getDefaultDataStorageTypeByPropType($propType),
        );

        $result = $this->setColumnAttributes(
            $property,
            $result
        );

        $result = $this->setIdAttributes(
            $property,
            $result
        );

        $result = $this->setGetterSetterAttributes(
            $property,
            $result
        );

        return $this->isValidDataStorageProp($result)
            ? $result
            : null;
    }

    protected function getDefaultDataStorageTypeByPropType(string $propType): string {
        if ($this->isDataStorageEntityType($propType)) {
            return "int";
        }

        return match ($propType) {
            "DateTime", "bool" => "int",
            default            => $propType
        };
    }

    protected function isValidDataStorageProp(DataStoragePropertyDTO $dataStoragePropertyDTO): bool {
        return $dataStoragePropertyDTO->getPropName() && $dataStoragePropertyDTO->getColumn();
    }

    protected function setIdAttributes(
        ReflectionProperty     $prop,
        DataStoragePropertyDTO $dto
    ): DataStoragePropertyDTO {
        if ($this->getFirstAttributeOfType($prop, Id::class)) {
            $dto->setPropName($prop->getName())
                ->setIsPrimary(true);

            if (!$dto->getColumn()) {
                $dto->setColumn($prop->getName());
            }
        }

        return $dto;
    }

    protected function setColumnAttributes(
        ReflectionProperty     $prop,
        DataStoragePropertyDTO $dto
    ): DataStoragePropertyDTO {
        if ($columnAttr = $this->getFirstAttributeOfType($prop, Column::class)) {
            /** @var Column $instance */
            $instance = $columnAttr->newInstance();
            $propName = $prop->getName();

            $dto->setPropName($propName)
                ->setColumn(
                    $this->getDataStorageColumnName($instance, $prop)
                )
                ->setDataStorageType(
                    $instance->type
                    ?? $this->getDataStorageTypeByPropType((string)$prop->getType()?->getName())
                );
        }

        return $dto;
    }

    protected function getDataStorageColumnName(Column $columnAttr, ReflectionProperty $prop): string {
        if ($columnAttr->name) {
            return $columnAttr->name;
        }

        $columnName = $this->getDataStorageColumnNameByPropName($prop->getName());

        if (
            !str_ends_with($columnName, "_id")
            && $this->isDataStorageEntityType(
                (string)$prop->getType()?->getName()
            )
        ) {
            $columnName .= "_id";
        }

        return $columnName;
    }

    protected function isDataStorageEntityType(string $fqn): bool {
        return class_exists($fqn)
            && ($parents = class_parents($fqn))
            && in_array(AEntity::class, $parents, true);
    }

    protected function getDataStorageColumnNameByPropName(string $propName): string {
        // Convert camelCase into snake_case
        return strtolower(
            preg_replace('/(?<!^)[A-Z]/', '_$0', $propName)
        );
    }

    protected function getDataStorageTypeByPropType(string $propType): string {
        return match ($propType) {
            'array'            => "string",
            default            => $this->getDefaultDataStorageTypeByPropType($propType)
        };
    }

    protected function setGetterSetterAttributes(
        ReflectionProperty     $prop,
        DataStoragePropertyDTO $dto
    ): DataStoragePropertyDTO {
        if ($this->getFirstAttributeOfType($prop, NoGetter::class)) {
            $dto->setDefaultGetter(false);
        }

        if ($this->getFirstAttributeOfType($prop, NoSetter::class)) {
            $dto->setDefaultSetter(false);
        }

        return $dto;
    }

    public function buildDataStorageReference(ReflectionProperty $property): ?DataStorageReferenceDTO {
        if ($refAttr = $this->getFirstAttributeOfType($property, Reference::class)) {
            $instance = $refAttr->newInstance();

            $result = new DataStorageReferenceDTO(
                propName: $property->getName(),
                refModel: $instance->refModel,
                refProp: $instance->refProp,
                refOrderBy: $instance->refOrderBy
            );

            return $this->isValidDataStorageReference($result)
                ? $result
                : null;
        }

        return null;
    }

    protected function getFirstAttributeOfType(ReflectionProperty $prop, string $attributeFQN): ?ReflectionAttribute {
        return $prop->getAttributes($attributeFQN)[0] ?? null;
    }

    protected function isValidDataStorageReference(DataStorageReferenceDTO $ref): bool {
        return $ref->propName
            && $ref->refModel
            && $ref->refProp;
    }

    public function buildTableName(ReflectionProperty $property): ?string {
        /** @var Table $instance */
        $instance = ($tableNameAttr = $this->getFirstAttributeOfType($property, Table::class))
            ? $tableNameAttr->newInstance()
            : null;

        return $instance?->tableName;
    }
}
<?php

declare(strict_types=1);

namespace Runway\Model\Provider;

use Runway\DataStorage\Attribute\Table;
use Runway\Model\DataBuilder\IDataStoragePropertyBuilder;
use Runway\Model\DTO\DataStoragePropertyDTO;
use Runway\Model\DTO\DataStorageReferenceDTO;
use Runway\Model\Exception\ModelException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

class DataStoragePropertiesProvider implements IDataStoragePropertiesProvider {
    protected string $modelFQN = '';

    protected ReflectionClass $reflection;

    public function __construct(
        protected IDataStoragePropertyBuilder $propBuilder
    ) {}

    /**
     * @return DataStoragePropertyDTO[]
     * @throws ModelException
     */
    public function getDataStorageProperties(string $modelFQN): array {
        $this->defineReflection($modelFQN);

        return array_values(
            array_filter(
                array_map(
                    fn(ReflectionProperty $prop): ?DataStoragePropertyDTO => $this->propBuilder->buildDataStorageProperty($prop),
                    $this->reflection->getProperties()
                )
            )
        );
    }

    public function getDataStorageReferences(string $modelFQN): array {
        $this->defineReflection($modelFQN);

        return array_values(
            array_filter(
                array_map(
                    fn(ReflectionProperty $prop): ?DataStorageReferenceDTO => $this->propBuilder->buildDataStorageReference($prop),
                    $this->reflection->getProperties()
                )
            )
        );
    }

    /**
     * @throws ModelException
     */
    public function getTableName(string $modelFQN): string {
        $this->defineReflection($modelFQN);

        foreach ($this->reflection->getAttributes() as $attr) {
            $instance = $attr->newInstance();

            if ($instance instanceof Table) {
                return $instance->tableName;
            }
        }

        return "";
    }

    /**
     * @throws ModelException
     */
    protected function defineReflection($modelFQN): void {
        if ($modelFQN !== $this->modelFQN) {
            $this->modelFQN = $modelFQN;

            try {
                $this->reflection = new ReflectionClass($this->modelFQN);
            } catch (ReflectionException $e) {
                throw new ModelException(
                    $e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }
        }
    }
}
<?php

declare(strict_types=1);

namespace Runway\Model\Provider;

use Runway\DataStorage\Attribute\Table;
use Runway\Model\DataBuilder\IDataStoragePropertyBuilder;
use Runway\Model\DTO\DataStoragePropertyDTO;
use Runway\Model\DTO\DataStorageReferenceDTO;
use Runway\Model\Exception\ModelException;
use ReflectionClass;
use ReflectionProperty;

class DataStoragePropertiesProvider implements IDataStoragePropertiesProvider {
    protected string $modelFQN = '';

    /**
     * @var ReflectionClass<object>
     */
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
    protected function defineReflection(string $modelFQN): void {
        if ($modelFQN !== $this->modelFQN) {
            if (!class_exists($modelFQN)) {
                throw new ModelException("Class \"$modelFQN\" does not exist");
            }

            $this->reflection = new ReflectionClass($modelFQN);
            $this->modelFQN = $modelFQN;
        }
    }
}
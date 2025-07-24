<?php

declare(strict_types=1);

namespace Runway\Model\Converter;

use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
use Runway\Exception\RuntimeException;
use Runway\Logger\ILogger;
use Runway\Model\AEntity;
use Runway\Model\Exception\ModelException;
use Runway\Singleton\IConverter;
use JsonException;
use ReflectionClass;
use ReflectionException;

class DataStoragePropertiesConverter implements IDataStoragePropertiesConverter {
    public function __construct(
        protected ILogger    $logger,
        protected IConverter $converter
    ) {}

    /**
     * @throws ModelException
     * @throws QueryBuilderException
     * @throws DBException
     */
    public function convert(string $fromType, string $toType, mixed $value): mixed {
        if ($fromType === $toType) {
            return $value;
        }

        $result = $value;

        if ($fromType === "int" && $toType === "DateTime") {
            $result = $this->converter->timestampToDateTime($value);
        } elseif ($fromType === "DateTime" && $toType === "int") {
            $result = $this->converter->dateTimeToTimestamp($value);

            // get model entity by id.
        } elseif ($fromType === "int" && $value !== null && $this->isModelFQN($toType)) {
            $result = new $toType($value);

            // get model entity id
        } elseif ($toType === "int" && $value !== null && $this->isModelFQN($fromType)) {
            if ($value instanceof AEntity) {
                if (!$value->isPersistent()) {
                    $value->persist();

                    // Try persisting, expect unique identifier to be non-empty after this.
                    // If it's still empty, it means entity is already in the process of persisting.
                    // So, we are in the infinite loop / circular persist dependency / something like that.
                    if (!$value->isPersistent()) {
                        throw new RuntimeException("Infinite loop while trying to persist entities.");
                    }
                }

                return (int)$value->getUniqueIdentifier();
            }

            return (int)$value;

            // when converting from string to array, string is expected to be JSON-encoded array
        } elseif ($fromType === "string" && $toType === "array") {
            try {
                $result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->logger->warning(
                    __CLASS__ . ": error while converting from JSON string to array. Reason: {$e->getMessage()}"
                );

                $result = [];
            }

            // when converting from array to string, just JSON-encode an array
        } elseif ($fromType === "array" && $toType === "string") {
            try {
                $result = json_encode($result, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->logger->warning(
                    __CLASS__ . ": error while converting from array to JSON string. Reason: {$e->getMessage()}"
                );

                $result = "";
            }
        } elseif ($fromType === "int" && $toType === "bool") {
            $result = (bool)$value;
        } elseif ($fromType === "bool" && $toType === "int") {
            $result = $value ? 1 : 0;
        }

        return $result;
    }

    protected function isModelFQN(string $fqn): bool {
        if ($this->isSimpleType($fqn)) {
            return false;
        }

        try {
            return new ReflectionClass($fqn)->isSubclassOf(AEntity::class);
        } catch (ReflectionException $e) {
            $this->logger->warning(
                __METHOD__ . ": cannot get a reflection of $fqn. Reason: {$e->getMessage()}."
            );
        }

        return false;
    }

    protected function isSimpleType(string $type): bool {
        return !str_contains($type, "\\");
    }
}
<?php

declare(strict_types=1);

namespace Runway\Model\Converter;

use Runway\DataStorage\Exception\DBException;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
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
            /** @var AEntity $result */
            $result = is_numeric($value) ? $result : $result->getUniqueIdentifier();

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
        try {
            return new ReflectionClass($fqn)->isSubclassOf(AEntity::class);
        } catch (ReflectionException $e) {
            $this->logger->warning(
                __METHOD__ . ": cannot get a reflection of $fqn. Reason: {$e->getMessage()}."
            );
        }

        return false;
    }
}
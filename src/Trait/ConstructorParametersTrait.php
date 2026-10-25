<?php

namespace Runway\Trait;

use Runway\Exception\RuntimeException;
use Runway\Singleton\Container;
use ReflectionClass;
use ReflectionNamedType;

trait ConstructorParametersTrait {
    /**
     * @param class-string|object|null $classOrFqn
     * @param array<string, mixed>     $definedParameters
     *
     * @return array<string, mixed>
     */
    protected static function getConstructorParameters(
        string|object|null $classOrFqn = null,
        array              $definedParameters = []
    ): array {
        $classOrFqn ??= static::class;
        $params = [];

        $constructorReflection = new ReflectionClass($classOrFqn)->getConstructor();

        foreach ($constructorReflection?->getParameters() ?? [] as $param) {
            $paramName = $param->getName();

            if (array_key_exists($paramName, $definedParameters)) {
                $params[$paramName] = $definedParameters[$paramName];

                continue;
            }

            $isOptional = $param->isDefaultValueAvailable();
            $defaultValue = $isOptional ? $param->getDefaultValue() : null;

            $type = $param->getType();
            $paramType = ($type instanceof ReflectionNamedType) ? $type->getName() : '';
            $service = $paramType
                ? Container::getInstance()->tryGetService($paramType)
                : null;

            if (!$isOptional && !$service) {
                throw new RuntimeException(
                    "Cannot instantiate " . static::class . ": parameter \${$paramName} should be either a service or have default value."
                );
            }

            $params[$paramName] = $service ?? $defaultValue;
        }

        return $params;
    }
}

<?php

namespace Runway\Trait;

use Runway\Exception\RuntimeException;
use Runway\Singleton\Container;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;

trait ConstructorParametersTrait {
    /**
     * @return array<string, mixed>
     */
    protected static function getConstructorParameters(
        string|object|null $classOrFqn = null,
        array              $definedParameters = []
    ): array {
        $classOrFqn ??= static::class;
        $params = [];

        $constructorReflection = new ReflectionClass($classOrFqn)->getConstructor();
        /** @var ReflectionParameter $param */
        foreach ((array)$constructorReflection?->getParameters() as $param) {
            if (array_key_exists($param->getName(), $definedParameters)) {
                $params[$param->getName()] = $definedParameters[$param->getName()];

                continue;
            }

            try {
                $isOptional = true;
                $defaultValue = $param->getDefaultValue();
            } catch (ReflectionException) {
                $isOptional = false;
                $defaultValue = null;
            }

            $paramName = $param->getName();
            $paramType = (string)$param->getType()?->getName();
            $service = $paramType
                ? Container::getInstance()->tryGetService($paramType)
                : null;

            if (
                !$isOptional
                && (
                    !$paramType
                    || !$service
                )
            ) {
                throw new RuntimeException(
                    "Cannot instantiate " . static::class . ": parameter \${$paramName} should be either a service or have default value."
                );
            }

            $params[$paramName] = $service ?? $defaultValue;
        }

        return $params;
    }
}
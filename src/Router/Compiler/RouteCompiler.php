<?php

declare(strict_types=1);

namespace Runway\Router\Compiler;

use Runway\Router\DTO\CompiledRouteDTO;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\Exception\Route\RouteException;

/**
 * Compiles a path template into a regexp. A template is a path with placeholders:
 *
 *     /chats/{chatId}/chunks/{index}
 *     /files/{name}.{extension}
 *     /docs/{path<.+>}               an inline requirement: the regexp of the value (here it can contain slashes)
 *
 * A placeholder value matches the route requirement for it, or the default one (any text without slashes).
 *
 * The specificity defines which route wins if several ones match the path: the segments are compared from left to
 * right, a static segment beats a segment with placeholders, which beats a catch-all one. E.g. "/contacts/search"
 * beats "/contacts/{contactId}", which beats "/contacts/{path<.+>}", whatever order they are declared in.
 */
class RouteCompiler implements IRouteCompiler {
    public const int SPECIFICITY_STATIC = 3;

    public const int SPECIFICITY_MIXED = 2;

    public const int SPECIFICITY_PLACEHOLDER = 1;

    public const int SPECIFICITY_CATCH_ALL = 0;

    protected const string DELIMITER = '#';

    protected const string NAME_REGEXP = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * @param string $defaultRequirement The regexp of a placeholder value without a requirement.
     */
    public function __construct(
        protected string $defaultRequirement = '[^/]+'
    ) {}

    public function compile(RouteDTO $route): CompiledRouteDTO {
        $path = '/' . ltrim($route->path, '/');
        $regexpParts = [];
        $variables = [];
        $specificity = [];

        foreach (explode('/', substr($path, 1)) as $segment) {
            $parts = $this->parseSegment($route, $segment);
            $segmentRegexp = '';
            $segmentRequirements = [];

            foreach ($parts as $part) {
                if (is_string($part)) {
                    $segmentRegexp .= preg_quote($part, static::DELIMITER);
                    continue;
                }

                [$name, $inlineRequirement] = $part;

                if (in_array($name, $variables, true)) {
                    throw new RouteException($route, "Route \"$route->name\": placeholder {{$name}} is used twice.");
                }

                $requirement = $inlineRequirement ?? $route->requirements[$name] ?? $this->defaultRequirement;
                $this->checkRequirement($route, $name, $requirement);

                $variables[] = $name;
                $segmentRequirements[] = $requirement;
                $segmentRegexp .= "(?P<$name>" . $this->escapeDelimiter($requirement) . ')';
            }

            $regexpParts[] = $segmentRegexp;
            $specificity[] = $this->getSegmentSpecificity($parts, $segmentRequirements);
        }

        return new CompiledRouteDTO(
            route: $route,
            regexp: static::DELIMITER . '^/' . implode('/', $regexpParts) . '$' . static::DELIMITER . 'u',
            variables: $variables,
            staticPath: $variables ? null : $path,
            specificity: $specificity
        );
    }

    /**
     * @return array<string|array{0: string, 1: string|null}> Literal texts and placeholders (name, inline requirement).
     *
     * @throws RouteException
     */
    protected function parseSegment(RouteDTO $route, string $segment): array {
        $parts = [];
        $offset = 0;
        $length = strlen($segment);

        while ($offset < $length) {
            $start = strpos($segment, '{', $offset);

            if ($start === false) {
                $parts[] = substr($segment, $offset);
                break;
            }

            if ($start > $offset) {
                $parts[] = substr($segment, $offset, $start - $offset);
            }

            [$parts[], $offset] = $this->parsePlaceholder($route, $segment, $start);
        }

        return $parts;
    }

    /**
     * @param int $start The position of "{".
     *
     * @return array{0: array{0: string, 1: string|null}, 1: int} The placeholder and the position after it.
     *
     * @throws RouteException
     */
    protected function parsePlaceholder(RouteDTO $route, string $segment, int $start): array {
        $nameEnd = strcspn($segment, '<}', $start + 1) + $start + 1;
        $name = substr($segment, $start + 1, $nameEnd - $start - 1);

        if (!preg_match(static::NAME_REGEXP, $name)) {
            throw new RouteException($route, "Route \"$route->name\": invalid placeholder name \"$name\" in $route->path.");
        }

        if (($segment[$nameEnd] ?? '') === '}') {
            return [[$name, null], $nameEnd + 1];
        }

        if (($segment[$nameEnd] ?? '') === '<') {
            // The requirement itself can contain "}" and ">" (e.g. "\d{4}"), so it ends with ">}".
            $requirementEnd = strpos($segment, '>}', $nameEnd + 1);

            if ($requirementEnd !== false) {
                return [
                    [$name, substr($segment, $nameEnd + 1, $requirementEnd - $nameEnd - 1)],
                    $requirementEnd + 2,
                ];
            }
        }

        throw new RouteException($route, "Route \"$route->name\": placeholder {{$name} is not closed in $route->path.");
    }

    /**
     * @throws RouteException
     */
    protected function checkRequirement(RouteDTO $route, string $name, string $requirement): void {
        if ($requirement === '' || @preg_match($this->getAnchoredRegexp($requirement), '') === false) {
            throw new RouteException(
                $route,
                "Route \"$route->name\": invalid requirement \"$requirement\" of placeholder {{$name}}."
            );
        }
    }

    /**
     * @param array<string|array> $parts        See parseSegment().
     * @param string[]            $requirements The requirements of the segment placeholders.
     */
    protected function getSegmentSpecificity(array $parts, array $requirements): int {
        if (!$requirements) {
            return static::SPECIFICITY_STATIC;
        }

        // A placeholder that can take several segments.
        foreach ($requirements as $requirement) {
            if (preg_match($this->getAnchoredRegexp($requirement), '/')) {
                return static::SPECIFICITY_CATCH_ALL;
            }
        }

        return (count($parts) > 1)
            ? static::SPECIFICITY_MIXED
            : static::SPECIFICITY_PLACEHOLDER;
    }

    protected function getAnchoredRegexp(string $requirement): string {
        return static::DELIMITER . '^(?:' . $this->escapeDelimiter($requirement) . ')$' . static::DELIMITER . 'u';
    }

    protected function escapeDelimiter(string $requirement): string {
        return preg_replace('/(?<!\\\\)' . preg_quote(static::DELIMITER, '/') . '/', '\\' . static::DELIMITER, $requirement);
    }
}

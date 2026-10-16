<?php

declare(strict_types=1);

namespace Runway\Router\DTO;

use Runway\Service\DTO\RouteDTO;

readonly class CompiledRouteDTO {
    /**
     * @param string      $regexp      Matches the whole (URL-decoded) path, the placeholders are named groups.
     * @param string[]    $variables   Placeholder names in the order of appearance.
     * @param string|null $staticPath  The path itself if it has no placeholders: it's compared as a string.
     * @param int[]       $specificity A score of every path segment, see RouteCompiler::getSegmentSpecificity().
     */
    public function __construct(
        public RouteDTO $route,
        public string   $regexp,
        public array    $variables,
        public ?string  $staticPath,
        public array    $specificity
    ) {}
}

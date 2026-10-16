<?php

declare(strict_types=1);

namespace Runway\Service\DTO;

readonly class RouteDTO {
    /**
     * @param string                $path         Path template, e.g. "/chats/{chatId}/chunks/{index<\d+>}" (see
     *                                            RouteCompiler).
     * @param int                   $priority     The routes with a higher priority are matched first, the routes with
     *                                            the same priority are ordered by specificity.
     * @param string[]              $methods      Allowed HTTP methods in upper case, any method if empty.
     * @param array<string, string> $requirements Placeholder name => regexp of its value (without delimiters and
     *                                            anchors). An inline requirement in the path ("{index<\d+>}") wins.
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $controller,
        public string $method,
        public int    $priority,
        public string $description,
        public string $filePath,
        public array  $methods = [],
        public array  $requirements = []
    ) {}
}

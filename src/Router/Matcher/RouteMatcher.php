<?php

declare(strict_types=1);

namespace Runway\Router\Matcher;

use Runway\Router\Compiler\IRouteCompiler;
use Runway\Router\DTO\CompiledRouteDTO;
use Runway\Router\DTO\RouteMatchDTO;
use Runway\Router\DTO\RouteMatchResultDTO;
use Runway\Router\Provider\IRouteProvider;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\Exception\Route\RouteDeclaredMoreThanOnce;
use Runway\Service\Exception\Route\RouteException;
use Runway\Singleton\Container;

/**
 * Collects the routes of all the providers and finds the one for a request: the routes are checked by priority, then
 * by specificity (see RouteCompiler), the first one that matches the path and the method wins.
 *
 * Routes are checked one by one: it's fast enough for hundreds of them.
 */
class RouteMatcher implements IRouteMatcher {
    public const string ROUTE_PROVIDER_TAG = 'router.route_provider';

    /**
     * @var CompiledRouteDTO[]|null In the matching order.
     */
    protected ?array $routes = null;

    /**
     * @param IRouteProvider[]|null $routeProviders By default, the services with the ROUTE_PROVIDER_TAG tag.
     */
    public function __construct(
        protected IRouteCompiler $compiler,
        protected ?array         $routeProviders = null
    ) {}

    public function match(string $path, string $method): RouteMatchResultDTO {
        $method = strtoupper($method);
        $allowedMethods = [];

        foreach ($this->getRoutes() as $route) {
            $parameters = $this->matchPath($route, $path);

            if ($parameters === null) {
                continue;
            }

            if ($this->isMethodAllowed($route->route, $method)) {
                return new RouteMatchResultDTO(
                    new RouteMatchDTO($route->route, $parameters)
                );
            }

            $allowedMethods = [...$allowedMethods, ...$this->getAllowedMethods($route->route)];
        }

        return new RouteMatchResultDTO(null, array_values(array_unique($allowedMethods)));
    }

    /**
     * @return array<string, string>|null The placeholder values, null if the path does not match.
     */
    protected function matchPath(CompiledRouteDTO $route, string $path): ?array {
        if ($route->staticPath !== null) {
            return ($route->staticPath === $path) ? [] : null;
        }

        if (!preg_match($route->regexp, $path, $matches)) {
            return null;
        }

        $parameters = [];

        foreach ($route->variables as $variable) {
            $parameters[$variable] = $matches[$variable];
        }

        return $parameters;
    }

    protected function isMethodAllowed(RouteDTO $route, string $method): bool {
        return !$route->methods || in_array($method, $this->getAllowedMethods($route), true);
    }

    /**
     * HEAD is allowed wherever GET is: the response is the same, but the web server drops the body.
     *
     * @return string[]
     */
    protected function getAllowedMethods(RouteDTO $route): array {
        return in_array('GET', $route->methods, true)
            ? [...$route->methods, 'HEAD']
            : $route->methods;
    }

    /**
     * @return CompiledRouteDTO[]
     *
     * @throws RouteException
     */
    protected function getRoutes(): array {
        if ($this->routes === null) {
            $routes = [];

            foreach ($this->getRouteProviders() as $provider) {
                foreach ($provider->getRoutes() as $route) {
                    $routes[] = $this->compiler->compile($route);
                }
            }

            $this->checkConflicts($routes);
            $this->routes = $this->sortRoutes($routes);
        }

        return $this->routes;
    }

    /**
     * @return IRouteProvider[]
     */
    protected function getRouteProviders(): array {
        return $this->routeProviders ??= array_values(array_filter(
            Container::getInstance()->getServicesByTag(static::ROUTE_PROVIDER_TAG),
            static fn(mixed $provider): bool => $provider instanceof IRouteProvider
        ));
    }

    /**
     * Two routes with the same path (and the same requirements) and the same methods are a mistake: one of them would
     * never be used.
     *
     * @param CompiledRouteDTO[] $routes
     *
     * @throws RouteException
     */
    protected function checkConflicts(array $routes): void {
        /** @var array<string, CompiledRouteDTO> $routesByName */
        $routesByName = [];

        /** @var array<string, CompiledRouteDTO[]> $routesByRegexp */
        $routesByRegexp = [];

        foreach ($routes as $route) {
            if (isset($routesByName[$route->route->name])) {
                throw new RouteDeclaredMoreThanOnce($route->route);
            }

            $routesByName[$route->route->name] = $route;

            // "/chats/{chatId}" and "/chats/{id}" match the same paths.
            $pathKey = (string)preg_replace('/\(\?P<\w+>/', '(', $route->regexp);

            foreach ($routesByRegexp[$pathKey] ?? [] as $samePathRoute) {
                if (
                    $samePathRoute->route->priority === $route->route->priority
                    && $this->isMethodsOverlap($samePathRoute->route, $route->route)
                ) {
                    throw new RouteException(
                        $route->route,
                        "Route \"{$route->route->name}\" ({$route->route->path}) conflicts with route"
                        . " \"{$samePathRoute->route->name}\" ({$samePathRoute->route->path}) declared in"
                        . " {$samePathRoute->route->filePath}: they have the same path and methods."
                    );
                }
            }

            $routesByRegexp[$pathKey][] = $route;
        }
    }

    protected function isMethodsOverlap(RouteDTO $a, RouteDTO $b): bool {
        return !$a->methods || !$b->methods || array_intersect($a->methods, $b->methods);
    }

    /**
     * @param CompiledRouteDTO[] $routes
     *
     * @return CompiledRouteDTO[]
     */
    protected function sortRoutes(array $routes): array {
        // The sorting is stable: the routes with the same priority and specificity keep the declaration order.
        usort(
            $routes,
            fn(CompiledRouteDTO $a, CompiledRouteDTO $b): int => ($b->route->priority <=> $a->route->priority)
                ?: $this->compareSpecificity($b->specificity, $a->specificity)
        );

        return $routes;
    }

    /**
     * @param int[] $a
     * @param int[] $b
     */
    protected function compareSpecificity(array $a, array $b): int {
        foreach ($a as $index => $score) {
            if (!isset($b[$index])) {
                return 0;
            }

            if ($score !== $b[$index]) {
                return $score <=> $b[$index];
            }
        }

        return 0;
    }
}

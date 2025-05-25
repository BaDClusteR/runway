<?php

declare(strict_types=1);

namespace Runway\Router;

use Runway\Controller\IController404;
use Runway\Request\Response;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\Exception\Route\RouteControllerNotFound;
use Runway\Service\Exception\Route\RouteMethodNotFound;
use Runway\Service\Provider\ConfigProvider;
use Runway\Service\Provider\IConfigProvider;
use Runway\Singleton\Container;

class Router implements IRouter {
    protected IConfigProvider $configProvider;

    /**
     * @var RouteDTO[]|null
     */
    protected static ?array $routes = null;

    public function __construct(
        protected string $wildcardRegexp
    ) {
        $this->configProvider = new ConfigProvider();
    }

    public function process(string $path): Response {
        $container = Container::getInstance();

        foreach ($this->getRoutes() as $route) {
            $routeRegexp = $this->getRouteRegexp(
                $route
            );

            if (preg_match($routeRegexp, $path, $rawParameters)) {
                $parameters = array_filter(
                    $rawParameters,
                    static fn($key) => is_string($key),
                    ARRAY_FILTER_USE_KEY
                );

                if ($container->hasService($route->controller)) {
                    $controller = $container->getService($route->controller);
                } elseif (class_exists($route->controller)) {
                    $controller = new $route->controller();
                } else {
                    throw new RouteControllerNotFound($route);
                }

                if (method_exists($controller, $route->method)) {
                    if (
                        ($response = $controller->{$route->method}(...$parameters)) instanceof Response
                    ) {
                        return $response;
                    }
                } else {
                    throw new RouteMethodNotFound($route);
                }
            }
        }

        return $this->get404Controller()->run();
    }

    protected function get404Controller(): IController404 {
        return Container::getInstance()->getService(IController404::class);
    }

    protected function getRouteRegexp(RouteDTO $route): string {
        $path = $route->path;

        if (!str_starts_with($path, "/")) {
            $path = "/{$path}";
        }

        return preg_replace(
            '/\{(.*?)}/',
            "(?<$1>{$this->wildcardRegexp})",
            "/^" . str_replace("/", '\/', $path) . "$/"
        );
    }

    /**
     * @return RouteDTO[]
     */
    protected function getRoutes(): array {
        if (static::$routes === null) {
            $this->defineRoutes();
        }

        return static::$routes;
    }

    protected function defineRoutes(): void {
        static::$routes = $this->configProvider->getConfig()->getRouteConfig();

        usort(
            static::$routes,
            static fn(RouteDTO $a, RouteDTO $b): int => $a->priority <=> $b->priority
        );
    }
}
<?php

declare(strict_types=1);

namespace Runway\Router;

use Runway\Controller\IController404;
use Runway\Controller\IMethodNotAllowedController;
use Runway\Exception\RuntimeException;
use Runway\Request\IRequest;
use Runway\Request\Response;
use Runway\Router\DTO\RouteMatchDTO;
use Runway\Router\Matcher\IRouteMatcher;
use Runway\Service\Exception\Route\RouteControllerNotFound;
use Runway\Service\Exception\Route\RouteMethodNotFound;
use Runway\Singleton\Container;

class Router implements IRouter {
    public function __construct(
        protected IRouteMatcher $matcher,
        protected IRequest      $request
    ) {}

    public function process(string $path, string $method = 'GET'): Response {
        // The placeholder values come decoded: "/docs/My%20file" gives "My file".
        $result = $this->matcher->match(rawurldecode($path), $method);

        if ($result->match === null) {
            return $result->allowedMethods
                ? $this->getMethodNotAllowedController()->run($result->allowedMethods)
                : $this->get404Controller()->run();
        }

        $this->request->setRouteMatch($result->match);

        return $this->callController($result->match);
    }

    /**
     * The placeholder values are passed to the controller method by their names.
     *
     * @throws RouteControllerNotFound
     * @throws RouteMethodNotFound
     * @throws RuntimeException
     */
    protected function callController(RouteMatchDTO $match): Response {
        $route = $match->route;
        $container = Container::getInstance();

        if ($container->hasService($route->controller)) {
            $controller = $container->getService($route->controller);
        } elseif (class_exists($route->controller)) {
            $controller = new $route->controller();
        } else {
            throw new RouteControllerNotFound($route);
        }

        // A public method only: a protected or a private one would be an Error, not a clear configuration error.
        if (!is_callable([$controller, $route->method])) {
            throw new RouteMethodNotFound($route);
        }

        $response = $controller->{$route->method}(...$match->parameters);

        if (!$response instanceof Response) {
            throw new RuntimeException(
                "Controller $route->controller::$route->method of route \"$route->name\" returned "
                . get_debug_type($response) . ' instead of ' . Response::class . '.'
            );
        }

        return $response;
    }

    protected function get404Controller(): IController404 {
        return Container::getInstance()->getService(IController404::class);
    }

    protected function getMethodNotAllowedController(): IMethodNotAllowedController {
        return Container::getInstance()->getService(IMethodNotAllowedController::class);
    }
}

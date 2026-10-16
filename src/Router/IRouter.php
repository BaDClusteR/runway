<?php

namespace Runway\Router;

use Runway\Request\Response;

interface IRouter {
    /**
     * @param string $path   The request path, URL-encoded (as in the request URI).
     * @param string $method HTTP method.
     */
    public function process(string $path, string $method = 'GET'): Response;
}

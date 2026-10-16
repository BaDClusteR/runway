<?php

namespace Runway\Controller;

use Runway\Request\Response;

/**
 * The response when the path matches some routes, but none of them allows the request method.
 */
interface IMethodNotAllowedController {
    /**
     * @param string[] $allowedMethods The methods of the routes that match the path.
     */
    public function run(array $allowedMethods): Response;
}

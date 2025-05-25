<?php

namespace Runway\Router;

use Runway\Request\Response;

interface IRouter {
    public function process(string $path): Response;
}
<?php

namespace Runway\Controller;

use Runway\Request\Response;
use Throwable;

interface IExceptionController {
    public function run(Throwable $exception): Response;
}
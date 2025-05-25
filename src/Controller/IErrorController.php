<?php

namespace Runway\Controller;

use Runway\Request\Response;

interface IErrorController {
    public function run(
        int    $errno,
        string $errStr,
        string $errFile,
        int    $errLine
    ): Response;
}
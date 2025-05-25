<?php

declare(strict_types=1);

namespace Runway\Controller;

use Runway\Request\Response;

class ErrorController implements IErrorController {
    public function run(
        int    $errno,
        string $errStr,
        string $errFile,
        int    $errLine,
    ): Response {
        return new Response(
            500,
            "Error in $errFile on line $errLine: $errStr"
        );
    }
}
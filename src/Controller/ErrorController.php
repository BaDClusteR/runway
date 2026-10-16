<?php

declare(strict_types=1);

namespace Runway\Controller;

use Runway\Request\Response;
use Runway\Singleton\IKernel;

/**
 * The error details are shown in the debug mode only (the error itself is logged by the error handler, see
 * Request::getErrorHandler()).
 */
class ErrorController implements IErrorController {
    public function __construct(
        protected IKernel $kernel
    ) {}

    public function run(
        int    $errno,
        string $errStr,
        string $errFile,
        int    $errLine,
    ): Response {
        return new Response(
            500,
            $this->kernel->isDebugMode()
                ? "Error in $errFile on line $errLine: $errStr"
                : '500 error'
        );
    }
}

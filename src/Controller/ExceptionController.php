<?php

declare(strict_types=1);

namespace Runway\Controller;

use Runway\Exception\FatalErrorException;
use Runway\Logger\ILogger;
use Runway\Request\Response;
use Runway\Singleton\Container;
use Runway\Singleton\IKernel;
use Throwable;

/**
 * The details of the exception (the message, the file, etc.) are shown in the debug mode only: they can contain
 * paths, SQL queries and other internals. They are logged anyway.
 */
class ExceptionController implements IExceptionController {
    public function __construct(
        protected IKernel $kernel,
        protected ILogger $logger
    ) {}

    public function run(Throwable $exception): Response {
        if ($exception instanceof FatalErrorException) {
            return $this->getErrorController()->run(
                $exception->getErrno(),
                $exception->getErrStr(),
                $exception->getErrFile(),
                $exception->getErrLine()
            );
        }

        $this->logger->error(
            'Uncaught exception: ' . $exception->getMessage(),
            [
                'exception' => get_debug_type($exception),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                // Without the arguments: they can contain passwords and personal data.
                'trace'     => $exception->getTraceAsString(),
            ]
        );

        return new Response(
            500,
            $this->kernel->isDebugMode()
                ? "Exception in {$exception->getFile()} on line {$exception->getLine()}: {$exception->getMessage()}"
                : '500 error'
        );
    }

    protected function getErrorController(): IErrorController {
        return Container::getInstance()->getService(IErrorController::class);
    }
}

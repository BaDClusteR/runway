<?php

declare(strict_types=1);

namespace Runway\Controller;

use Runway\Exception\FatalErrorException;
use Runway\Request\Response;
use Runway\Singleton\Container;
use Throwable;

class ExceptionController implements IExceptionController {
    public function run(Throwable $exception): Response {
        if ($exception instanceof FatalErrorException) {
            return $this->getErrorController()->run(
                $exception->getErrno(),
                $exception->getErrStr(),
                $exception->getErrFile(),
                $exception->getErrLine()
            );
        }

        return new Response(
            500,
            "Exception in {$exception->getFile()} on line {$exception->getLine()}: {$exception->getMessage()}"
        );
    }

    protected function getErrorController(): IErrorController {
        return Container::getInstance()->getService(IErrorController::class);
    }
}
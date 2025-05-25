<?php

declare(strict_types=1);

namespace Runway\Service\Exception;

use Runway\Service\DTO\ServiceDTO;
use Throwable;

class ServiceDecoratorCallException extends ServiceException {
    public function __construct(
        ServiceDTO $service,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $service,
            "Cannot call decorator service {$service->getName()}, call {$service->getAncestorService()} instead.",
            $code,
            $previous
        );
    }
}
<?php

declare(strict_types=1);

namespace Runway\Service\Exception;

use Runway\Service\DTO\ServiceDTO;
use Throwable;

class ClassNotFoundException extends ServiceException {
    public function __construct(
        public readonly string $classFQN,
        ServiceDTO             $service,
        int                    $code = 0,
        ?Throwable             $previous = null
    ) {
        parent::__construct(
            $service,
            "class $classFQN not found (required in service {$service->getName()})",
            $code,
            $previous
        );
    }
}
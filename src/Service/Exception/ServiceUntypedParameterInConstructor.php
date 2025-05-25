<?php

declare(strict_types=1);

namespace Runway\Service\Exception;

use Runway\Service\DTO\ServiceDTO;
use Throwable;

class ServiceUntypedParameterInConstructor extends ServiceException {
    public function __construct(
        ServiceDTO                $serviceDTO,
        protected readonly string $parameter,
        int                       $code = 0,
        ?Throwable                $previous = null
    ) {
        parent::__construct(
            $serviceDTO,
            "Parameter $parameter in the service's {$serviceDTO->getName()} constructor is untyped.",
            $code,
            $previous
        );
    }

    public function getParameter(): string {
        return $this->parameter;
    }
}
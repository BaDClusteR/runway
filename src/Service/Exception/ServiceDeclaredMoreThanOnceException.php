<?php

declare(strict_types=1);

namespace Runway\Service\Exception;

use Runway\Service\DTO\ServiceDTO;
use Throwable;

class ServiceDeclaredMoreThanOnceException extends ServiceException {
    public function __construct(
        ServiceDTO                    $service,
        protected readonly ServiceDTO $secondDeclaration,
        int                           $code = 0,
        ?Throwable                    $previous = null
    ) {
        parent::__construct(
            $service,
            "Service {$service->getName()} is declared more than once (found in {$service->getFilePath()} and in {$this->secondDeclaration->getFilePath()})",
            $code,
            $previous
        );
    }

    public function getSecondDeclaration(): ServiceDTO {
        return $this->secondDeclaration;
    }
}
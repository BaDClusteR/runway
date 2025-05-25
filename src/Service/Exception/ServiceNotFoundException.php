<?php

declare(strict_types=1);

namespace Runway\Service\Exception;

use Runway\Service\DTO\ServiceDTO;
use Throwable;

class ServiceNotFoundException extends ServiceException {
    public function __construct(
        ServiceDTO $serviceDTO,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($serviceDTO, "Service {$serviceDTO->getName()} not found.", $code, $previous);
    }
}
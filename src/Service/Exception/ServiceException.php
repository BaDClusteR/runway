<?php

declare(strict_types=1);

namespace Runway\Service\Exception;

use Runway\Service\DTO\ServiceDTO;
use Throwable;

class ServiceException extends ConfigurationException {
    public function __construct(
        public readonly ServiceDTO $service,
        string                     $message,
        int                        $code = 0,
        ?Throwable                 $previous = null
    ) {
        parent::__construct($this->service->getFilePath(), $message, $code, $previous);
    }
}
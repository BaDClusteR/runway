<?php

declare(strict_types=1);

namespace Runway\Event\Exception;

use Runway\Event\DTO\EventDTO;
use Runway\Exception\ConfigException;
use Runway\Service\DTO\ServiceDTO;
use Throwable;

class EventException extends ConfigException {
    public function __construct(
        protected readonly ServiceDTO $service,
        protected readonly EventDTO   $event,
        string                        $message = "",
        int                           $code = 0,
        ?Throwable                    $previous = null
    ) {
        parent::__construct("Configuration error in {$service->getFilePath()}: $message", $code, $previous);
    }

    public function getService(): ServiceDTO {
        return $this->service;
    }

    public function getEvent(): EventDTO {
        return $this->event;
    }
}
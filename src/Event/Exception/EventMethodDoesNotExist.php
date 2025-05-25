<?php

declare(strict_types=1);

namespace Runway\Event\Exception;

use Runway\Event\DTO\EventDTO;
use Runway\Service\DTO\ServiceDTO;
use Throwable;

class EventMethodDoesNotExist extends EventException {
    public function __construct(
        ServiceDTO $service,
        EventDTO   $event,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $service,
            $event,
            "Service {$service->getName()} does not have public method {$event->getMethod()} (called in event {$event->getName()}).",
            $code,
            $previous
        );
    }
}
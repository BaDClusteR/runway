<?php

declare(strict_types=1);

namespace Runway\Event\Exception;

use Runway\Event\DTO\EventDTO;
use Runway\Service\DTO\ServiceDTO;
use Throwable;

class EmptyEventMethod extends EventException {
    public function __construct(
        ServiceDTO $service,
        EventDTO   $event,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $service,
            $event,
            "Method name is not set in event {$event->getName()} (service {$service->getName()}).",
            $code,
            $previous
        );
    }
}
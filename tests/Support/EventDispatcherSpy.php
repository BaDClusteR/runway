<?php

declare(strict_types=1);

namespace Runway\Tests\Support;

use Runway\Event\IEventDispatcher;

final class EventDispatcherSpy implements IEventDispatcher {
    /**
     * @var list<array{string, mixed}> Event name and envelope.
     */
    public array $events = [];

    public function dispatch(string $eventName, mixed $envelope): void {
        $this->events[] = [$eventName, $envelope];
    }
}

<?php

namespace Runway\Event;

interface IEventDispatcher {
    public function dispatch(string $eventName, mixed $envelope): void;
}
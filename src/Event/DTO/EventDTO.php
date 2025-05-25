<?php

declare(strict_types=1);

namespace Runway\Event\DTO;

readonly class EventDTO {
    public function __construct(
        private string $name,
        private string $serviceName,
        private string $method
    ) {}

    public function getName(): string {
        return $this->name;
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getServiceName(): string {
        return $this->serviceName;
    }
}
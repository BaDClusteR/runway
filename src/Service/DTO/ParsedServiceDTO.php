<?php

declare(strict_types=1);

namespace Runway\Service\DTO;

use Runway\Event\DTO\EventDTO;

class ParsedServiceDTO {
    /**
     * @param ServiceArgumentDTO[] $arguments
     * @param EventDTO[]           $events
     * @param TagDTO[]             $tags
     */
    public function __construct(
        private readonly string $name,
        private readonly string $class,
        private readonly string $decorates = '',
        private readonly array  $arguments = [],
        private readonly array  $events = [],
        private readonly array  $tags = [],
        private string          $filePath = ''
    ) {}

    public function getName(): string {
        return $this->name;
    }

    public function getDecorates(): string {
        return $this->decorates;
    }

    public function getClass(): string {
        return $this->class;
    }

    /**
     * @return ServiceArgumentDTO[]
     */
    public function getArguments(): array {
        return $this->arguments;
    }

    public function getEvents(): array {
        return $this->events;
    }

    public function getTags(): array {
        return $this->tags;
    }

    public function getFilePath(): string {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static {
        $this->filePath = $filePath;

        return $this;
    }
}
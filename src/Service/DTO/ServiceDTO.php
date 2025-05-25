<?php

declare(strict_types=1);

namespace Runway\Service\DTO;

use Runway\Event\DTO\EventDTO;

class ServiceDTO {
    /**
     * @param string[]             $decorators
     * @param ServiceArgumentDTO[] $arguments
     * @param EventDTO[]           $events
     * @param TagDTO[]             $tags
     */
    public function __construct(
        private readonly string $name,
        private readonly string $class = '',
        private readonly string $decoratee = '',
        private string          $ancestorService = '',
        private array           $decorators = [],
        private readonly array  $arguments = [],
        private readonly array  $events = [],
        private readonly array  $tags = [],
        private readonly string $filePath = ''
    ) {}

    public function getName(): string {
        return $this->name;
    }

    public function getDecoratee(): string {
        return $this->decoratee;
    }

    public function getClass(): string {
        return $this->class;
    }

    public function addDecorator(string $decoratorFQN): static {
        if (!array_key_exists($decoratorFQN, $this->decorators)) {
            $this->decorators[] = $decoratorFQN;
        }

        return $this;
    }

    /**
     * @return string[]
     */
    public function getDecorators(): array {
        return $this->decorators;
    }

    /**
     * @return ServiceArgumentDTO[]
     */
    public function getArguments(): array {
        return $this->arguments;
    }

    public function isArgumentDefined($argumentName): bool {
        return array_any(
            $this->arguments,
            static fn($argument) => ($argument->getName() === $argumentName)
        );
    }

    public function getArgumentValue($argumentName): mixed {
        foreach ($this->arguments as $argument) {
            if ($argument->getName() === $argumentName) {
                return $argument->getValue();
            }
        }

        return null;
    }

    public function getAncestorService(): string {
        return $this->ancestorService;
    }

    public function setAncestorService(string $ancestorService): static {
        $this->ancestorService = $ancestorService;

        return $this;
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
}

<?php

declare(strict_types=1);

namespace Runway\Exception;

use Runway\Singleton\DTO\DependencyChainLinkDTO;
use Throwable;

class CircularDependencyException extends RuntimeException {
    /**
     * @param DependencyChainLinkDTO[] $dependencyChain
     */
    public function __construct(
        protected array $dependencyChain,
        ?Throwable      $previous = null
    ) {
        parent::__construct($this->getMessageText(), 0, $previous);
    }

    /**
     * @return DependencyChainLinkDTO[]
     */
    public function getDependencyChain(): array {
        return $this->dependencyChain;
    }

    public function getMessageText(): string {
        $text = "Circular dependency detected:\n";

        foreach ($this->dependencyChain as $i => $dependency) {
            $text .= ($i + 1)
                . ": {$dependency->serviceName} in {$dependency->fileName}, line {$dependency->line}: {$dependency->method}\n";
        }

        return $text;
    }
}
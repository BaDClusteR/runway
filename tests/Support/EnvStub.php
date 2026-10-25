<?php

declare(strict_types=1);

namespace Runway\Tests\Support;

use Runway\Env\Provider\IEnvVariablesProvider;

final class EnvStub implements IEnvVariablesProvider {
    /**
     * @param array<string, mixed> $variables
     */
    public function __construct(
        private readonly array $variables = []
    ) {}

    public function getEnvVariable(string $variableName): mixed {
        return $this->variables[$variableName] ?? null;
    }
}

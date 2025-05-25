<?php

namespace Runway\Env\Provider;

interface IEnvVariablesProvider {
    public function getEnvVariable(string $variableName): mixed;
}
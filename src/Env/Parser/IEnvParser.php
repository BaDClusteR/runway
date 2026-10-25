<?php

namespace Runway\Env\Parser;

use Runway\Env\Exception\EnvParserException;

interface IEnvParser {
    /**
     * @return array<mixed> Name => value (typed: "true" is a bool, "12" is an int).
     *
     * @throws EnvParserException
     */
    public function parseString(string $string): array;

    /**
     * @return array<mixed> Name => value (typed: "true" is a bool, "12" is an int).
     *
     * @throws EnvParserException
     */
    public function parseFile(string $filePath): array;
}
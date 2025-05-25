<?php

namespace Runway\Env\Parser;

use Runway\Env\Exception\EnvParserException;

interface IEnvParser {
    /**
     * @throws EnvParserException
     */
    public function parseString(string $string): array;

    /**
     * @throws EnvParserException
     */
    public function parseFile(string $filePath): array;
}
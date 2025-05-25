<?php

declare(strict_types=1);

namespace Runway\Env\Parser;

use Runway\Env\Exception\EnvParserException;

class EnvParser implements IEnvParser {
    /**
     * @throws EnvParserException
     */
    public function parseString(string $string): array {
        $result = parse_ini_string($string, false, INI_SCANNER_TYPED);

        if ($result === false) {
            throw new EnvParserException($string, "Cannot parse ENV string.");
        }

        return $result;
    }

    public function parseFile(string $filePath): array {
        $result = parse_ini_file($filePath, false, INI_SCANNER_TYPED);

        if ($result === false) {
            throw new EnvParserException(
                file_get_contents($filePath),
                "Cannot parse ENV file $filePath"
            );
        }

        return $result;
    }
}
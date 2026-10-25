<?php

declare(strict_types=1);

namespace Runway\Env\Parser;

use Runway\Env\Exception\EnvParserException;

class EnvParser implements IEnvParser {
    /**
     * @return array<mixed> Name => value (typed: "true" is a bool, "12" is an int).
     *
     * @throws EnvParserException
     */
    public function parseString(string $string): array {
        $result = @parse_ini_string($string, false, INI_SCANNER_TYPED);

        if ($result === false) {
            throw new EnvParserException($string, "Cannot parse ENV string.");
        }

        return $result;
    }

    /**
     * @return array<mixed>
     *
     * @throws EnvParserException
     */
    public function parseFile(string $filePath): array {
        $result = @parse_ini_file($filePath, false, INI_SCANNER_TYPED);

        if ($result === false) {
            // Not the file content: it has the secrets, and the exception can get to the logs or to the output.
            throw new EnvParserException(
                '',
                "Cannot parse ENV file $filePath: " . (error_get_last()['message'] ?? 'unknown error')
            );
        }

        return $result;
    }
}
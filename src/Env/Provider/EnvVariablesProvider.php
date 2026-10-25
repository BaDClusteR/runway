<?php

declare(strict_types=1);

namespace Runway\Env\Provider;

use Runway\Env\Exception\EnvParserException;
use Runway\Env\Parser\EnvParser;
use Runway\Env\Parser\IEnvParser;
use Runway\Service\Provider\PathsProvider;

class EnvVariablesProvider implements IEnvVariablesProvider {
    private IEnvParser $envParser;

    /**
     * @var array<mixed>|null All the files: the later ones override the earlier ones.
     */
    private static ?array $variables = null;

    /**
     * @throws EnvParserException
     */
    public function __construct() {
        if (self::$variables === null) {
            $this->envParser = new EnvParser();
            $this->defineEnvVariables();
        }
    }

    /**
     * @throws EnvParserException
     */
    protected function defineEnvVariables(): void {
        $variablesByFile = [];

        foreach ($this->getEnvFilePaths() as $filePath) {
            if (file_exists($filePath) && is_readable($filePath)) {
                $variablesByFile[] = $this->envParser->parseFile($filePath);
            }
        }

        self::$variables = array_merge(...$variablesByFile);
    }

    public function getEnvVariable(string $variableName): mixed {
        return self::$variables[$variableName] ?? null;
    }

    /**
     * @return string[]
     */
    protected function getEnvFilePaths(): array {
        return PathsProvider::getInstance()->getEnvFilePaths();
    }
}
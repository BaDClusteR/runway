<?php

declare(strict_types=1);

namespace Runway\Env\Provider;

use Runway\Env\Exception\EnvParserException;
use Runway\Env\Parser\EnvParser;
use Runway\Env\Parser\IEnvParser;
use Runway\Service\Provider\DirectoriesProvider;

class EnvVariablesProvider implements IEnvVariablesProvider {
    private IEnvParser $envParser;

    private static ?array $variables = null;

    /**
     * @throws EnvParserException
     */
    public function __construct() {
        if (static::$variables === null) {
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

        static::$variables = array_merge(...$variablesByFile);
    }

    public function getEnvVariable(string $variableName): mixed {
        return static::$variables[$variableName] ?? null;
    }

    /**
     * @return string[]
     */
    protected function getEnvFilePaths(): array {
        return DirectoriesProvider::getInstance()->getEnvFilePaths();
    }
}
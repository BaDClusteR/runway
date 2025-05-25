<?php

declare(strict_types=1);

namespace Runway\Env\Exception;

use Runway\Exception\Exception;
use Throwable;

class EnvParserException extends Exception {
    public function __construct(
        protected readonly string $envContent,
        string                    $message = "",
        int                       $code = 0,
        ?Throwable                $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getEnvContent(): string {
        return $this->envContent;
    }
}
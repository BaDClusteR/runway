<?php

declare(strict_types=1);

namespace Runway\Service\Exception;

use Runway\Exception\RuntimeException;
use Throwable;

class ConfigurationException extends RuntimeException {
    public function __construct(
        public readonly string $filePath,
        string                 $message = "",
        int                    $code = 0,
        ?Throwable             $previous = null
    ) {
        parent::__construct(
            $this->filePath
                ? "Configuration error in {$this->filePath}: {$message}"
                : "Configuration error: {$message}",
            $code,
            $previous
        );
    }
}
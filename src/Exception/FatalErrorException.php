<?php

declare(strict_types=1);

namespace Runway\Exception;

use Throwable;

class FatalErrorException extends Exception {
    public function __construct(
        protected int    $errno,
        protected string $errStr,
        protected string $errFile,
        protected int    $errLine,
        ?Throwable       $previous = null
    ) {
        parent::__construct("Fatal error in $this->errFile on line $this->errLine: $this->errStr", $errno, $previous);
    }

    public function getErrno(): int {
        return $this->errno;
    }

    public function getErrStr(): string {
        return $this->errStr;
    }

    public function getErrFile(): string {
        return $this->errFile;
    }

    public function getErrLine(): int {
        return $this->errLine;
    }
}
<?php

declare(strict_types=1);

namespace Runway\FileSystem\Exception;

use Throwable;

class CannotReadFileException extends FileSystemException {
    public function __construct(
        string     $filePath,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("Cannot read '$filePath'", $code, $previous);
    }
}
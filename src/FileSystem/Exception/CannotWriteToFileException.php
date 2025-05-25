<?php

declare(strict_types=1);

namespace Runway\FileSystem\Exception;

use Throwable;

class CannotWriteToFileException extends FileSystemException {
    public function __construct(
        string     $filePath,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("Cannot write to '$filePath'", $code, $previous);
    }
}
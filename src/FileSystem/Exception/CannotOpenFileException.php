<?php

declare(strict_types=1);

namespace Runway\FileSystem\Exception;

use Throwable;

class CannotOpenFileException extends FileSystemException {
    public function __construct(
        string     $filePath,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("Cannot create '$filePath'", $code, $previous);
    }
}
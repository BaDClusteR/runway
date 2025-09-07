<?php

declare(strict_types=1);

namespace Runway\FileSystem\Exception;

use Throwable;

class CannotDeleteFileException extends FileSystemException {
    public function __construct(
        string     $filePath,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("Cannot delete '$filePath'.", $code, $previous);
    }
}
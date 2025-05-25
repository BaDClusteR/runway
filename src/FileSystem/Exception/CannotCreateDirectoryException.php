<?php

declare(strict_types=1);

namespace Runway\FileSystem\Exception;

use Throwable;

class CannotCreateDirectoryException extends FileSystemException {
    public function __construct(
        string     $directory,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("Cannot create directory '$directory'", $code, $previous);
    }
}
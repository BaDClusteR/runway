<?php

declare(strict_types=1);

namespace Runway\FileSystem\Exception;

use Throwable;

class CannotCopyException extends FileCopyException {
    public function __construct(
        string     $srcPath,
        string     $dstPath,
        ?Throwable $previous = null
    ) {
        parent::__construct("Cannot copy $srcPath to $dstPath");
    }
}
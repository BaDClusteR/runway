<?php

namespace Runway\FileSystem\Exception;

class DestinationFileAlreadyExistsException extends FileCopyException {
    public function __construct(
        protected readonly string $filePath
    ) {
        parent::__construct("File already exists: $this->filePath");
    }
}
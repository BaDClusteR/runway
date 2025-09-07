<?php

namespace Runway\FileSystem;

use Runway\FileSystem\Exception\CannotCreateDirectoryException;
use Runway\FileSystem\Exception\FileSystemException;

interface IFileSystem {
    /**
     * @throws FileSystemException
     */
    public function append(string $filePath, mixed $data): void;

    /**
     * @throws FileSystemException
     */
    public function touch(string $filePath): void;

    /**
     * @throws CannotCreateDirectoryException
     */
    public function mkdir(string $directory, int $mode = 0777): void;

    /**
     * @throws FileSystemException
     */
    public function copy(
        string $srcPath,
        string $dstPath,
        bool   $isOverwrite = false,
        bool   $isChangeFilenameIfExists = true
    ): string;

    public function remove(string $fullPath): void;
}
<?php

declare(strict_types=1);

namespace Runway\FileSystem;

use Runway\FileSystem\Exception\CannotCopyException;
use Runway\FileSystem\Exception\CannotCreateDirectoryException;
use Runway\FileSystem\Exception\CannotFindFileException;
use Runway\FileSystem\Exception\CannotOpenFileException;
use Runway\FileSystem\Exception\CannotReadFileException;
use Runway\FileSystem\Exception\CannotWriteToFileException;
use Runway\FileSystem\Exception\DestinationFileAlreadyExistsException;
use Runway\FileSystem\Exception\FileSystemException;

class FileSystem implements IFileSystem {
    protected const int MAX_FILE_SUFFIX = 10000;

    /**
     * @throws FileSystemException
     */
    public function append(string $filePath, mixed $data): void {
        $f = $this->fopen($filePath, "ab+");
        $this->fwrite($f, $filePath, $data);
        fclose($f);
    }

    /**
     * @throws FileSystemException
     */
    public function touch(string $filePath): void {
        $f = $this->fopen($filePath, "w");
        fclose($f);
    }

    /**
     * @throws FileSystemException
     */
    protected function fopen(string $filePath, string $mode) {
        $this->mkdir(
            dirname($filePath)
        );

        $f = fopen($filePath, $mode);

        if (!$f) {
            throw new CannotOpenFileException($filePath);
        }

        return $f;
    }

    /**
     * @throws CannotWriteToFileException
     */
    protected function fwrite($f, string $filePath, mixed $data): void {
        if (fwrite($f, $data) === false) {
            throw new CannotWriteToFileException($filePath);
        }
    }

    /**
     * @throws CannotCreateDirectoryException
     */
    public function mkdir(string $directory, int $mode = 0777): void {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, $mode, true) && !is_dir($directory)) {
            throw new CannotCreateDirectoryException($directory);
        }
    }

    /**
     * @throws FileSystemException
     */
    public function copy(
        string $srcPath,
        string $dstPath,
        bool   $isOverwrite = false,
        bool   $isChangeFilenameIfExists = true
    ): string {
        $this->validateFileExistsAndReadable($srcPath);

        $dstPath = $this->getDstFilePathForCopy($dstPath, $isOverwrite, $isChangeFilenameIfExists);
        $this->mkdir(
            dirname($dstPath)
        );

        $copyResult = copy($srcPath, $dstPath);

        if (!$copyResult) {
            throw new CannotCopyException($srcPath, $dstPath);
        }

        return $dstPath;
    }

    /**
     * @throws CannotFindFileException
     * @throws CannotReadFileException
     */
    protected function validateFileExistsAndReadable(string $filePath): void {
        if (!file_exists($filePath)) {
            throw new CannotFindFileException($filePath);
        }

        if (!is_readable($filePath)) {
            throw new CannotReadFileException("Cannot read $filePath.");
        }
    }

    /**
     * @throws DestinationFileAlreadyExistsException
     */
    protected function getDstFilePathForCopy(
        string $dstPath,
        bool   $isOverwrite,
        bool   $isChangeFilenameIfExists
    ): string {
        return dirname($dstPath)
            . DIRECTORY_SEPARATOR
            . $this->getDstFilenameForCopy(
                $dstPath,
                $isOverwrite,
                $isChangeFilenameIfExists
            );
    }

    /**
     * @throws DestinationFileAlreadyExistsException
     */
    protected function getDstFilenameForCopy(
        string $dstPath,
        bool   $isOverwrite,
        bool   $isChangeFilenameIfExists
    ): string {
        if ($isOverwrite || !file_exists($dstPath)) {
            return basename($dstPath);
        }

        if (!$isChangeFilenameIfExists) {
            throw new DestinationFileAlreadyExistsException($dstPath);
        }

        $newDstPath = $this->getChangedDstPathForCopy($dstPath);

        if (file_exists($newDstPath)) {
            throw new DestinationFileAlreadyExistsException($newDstPath);
        }

        return basename($newDstPath);
    }

    protected function getChangedDstPathForCopy(string $dstPath): string {
        $dstDir = dirname($dstPath);
        $dstBasename = basename($dstPath);
        $dstFileExtension = pathinfo($dstBasename, PATHINFO_EXTENSION);
        $dstFilename = pathinfo($dstBasename, PATHINFO_FILENAME);
        $suffix = 0;

        while (
            file_exists(
                $newDstPath = (
                    $dstDir . DIRECTORY_SEPARATOR . $dstFilename
                    . ($suffix ? "-$suffix" : "")
                    . ($dstFileExtension ? "." . $dstFileExtension : "")
                )
            )
            && $suffix < static::MAX_FILE_SUFFIX
        ) {
            $suffix++;
        }

        return $newDstPath;
    }
}
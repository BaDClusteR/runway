<?php

declare(strict_types=1);

namespace Runway\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A new empty directory for every test, removed after it.
 */
trait TemporaryDirectory {
    protected string $directory = '';

    protected function createTemporaryDirectory(): string {
        $this->directory = sys_get_temp_dir() . '/runway-test-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0777, true);

        return $this->directory;
    }

    protected function removeTemporaryDirectory(): void {
        if ($this->directory !== '' && is_dir($this->directory)) {
            self::removeDirectory($this->directory);
        }

        $this->directory = '';
    }

    protected static function removeDirectory(string $path): void {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo) {
                // A read-only file or directory of a test.
                @chmod($file->getPathname(), 0777);
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
        }

        rmdir($path);
    }

    /**
     * Creates the file (and its directories) in the temporary directory.
     */
    protected function createFile(string $relativePath, string $content = ''): string {
        $path = "$this->directory/$relativePath";

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $content);

        return $path;
    }
}

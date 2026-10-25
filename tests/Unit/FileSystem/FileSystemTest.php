<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\FileSystem;

use PHPUnit\Framework\TestCase;
use Runway\FileSystem\Exception\CannotCopyException;
use Runway\FileSystem\Exception\CannotCreateDirectoryException;
use Runway\FileSystem\Exception\CannotDeleteFileException;
use Runway\FileSystem\Exception\CannotFindFileException;
use Runway\FileSystem\Exception\CannotOpenFileException;
use Runway\FileSystem\Exception\CannotReadFileException;
use Runway\FileSystem\Exception\CannotWriteToFileException;
use Runway\FileSystem\Exception\DestinationFileAlreadyExistsException;
use Runway\FileSystem\FileSystem;
use Runway\Tests\Support\TemporaryDirectory;

class FileSystemTest extends TestCase {
    use TemporaryDirectory;

    protected FileSystem $fileSystem;

    protected function setUp(): void {
        $this->createTemporaryDirectory();
        $this->fileSystem = new FileSystem();
    }

    protected function tearDown(): void {
        $this->removeTemporaryDirectory();
    }

    public function testAppendCreatesDirectoriesAndAppends(): void {
        $path = "$this->directory/a/b/log.txt";

        $this->fileSystem->append($path, "first\n");
        $this->fileSystem->append($path, 42);

        self::assertSame("first\n42", file_get_contents($path));
    }

    public function testAppendOfNotStringData(): void {
        $this->expectException(CannotWriteToFileException::class);
        $this->expectExceptionMessage("Cannot write to '$this->directory/log.txt'");

        $this->fileSystem->append("$this->directory/log.txt", ['array']);
    }

    public function testTouchEmptiesFile(): void {
        $path = $this->createFile('a/file.txt', 'content');

        $this->fileSystem->touch($path);
        $this->fileSystem->touch("$this->directory/new/file.txt");

        self::assertSame('', file_get_contents($path));
        self::assertFileExists("$this->directory/new/file.txt");
    }

    public function testCannotOpenFile(): void {
        mkdir("$this->directory/directory");

        $this->expectException(CannotOpenFileException::class);
        $this->expectExceptionMessage("Cannot create '$this->directory/directory'");

        $this->fileSystem->touch("$this->directory/directory");
    }

    public function testMkdir(): void {
        $this->fileSystem->mkdir("$this->directory/a/b/c");
        $this->fileSystem->mkdir("$this->directory/a/b/c");

        self::assertDirectoryExists("$this->directory/a/b/c");
    }

    public function testCannotCreateDirectory(): void {
        $file = $this->createFile('file', 'content');

        $this->expectException(CannotCreateDirectoryException::class);
        $this->expectExceptionMessage("Cannot create directory '$file/sub'");

        $this->fileSystem->mkdir("$file/sub");
    }

    public function testCopy(): void {
        $source = $this->createFile('source.txt', 'content');

        $destination = $this->fileSystem->copy($source, "$this->directory/copies/copy.txt");

        self::assertSame("$this->directory/copies" . DIRECTORY_SEPARATOR . 'copy.txt', $destination);
        self::assertSame('content', file_get_contents($destination));
    }

    public function testCopyChangesNameIfFileExists(): void {
        $source = $this->createFile('source.txt', 'new');
        $this->createFile('copy.txt', 'old');
        $this->createFile('copy-1.txt', 'old 1');

        $destination = $this->fileSystem->copy($source, "$this->directory/copy.txt");

        self::assertSame("$this->directory" . DIRECTORY_SEPARATOR . 'copy-2.txt', $destination);
        self::assertSame('new', file_get_contents($destination));
        self::assertSame('old', file_get_contents("$this->directory/copy.txt"));
    }

    public function testCopyChangesNameOfFileWithoutExtension(): void {
        $source = $this->createFile('source', 'new');
        $this->createFile('copy', 'old');

        self::assertSame("$this->directory" . DIRECTORY_SEPARATOR . 'copy-1', $this->fileSystem->copy($source, "$this->directory/copy"));
    }

    public function testCopyOverwrites(): void {
        $source = $this->createFile('source.txt', 'new');
        $existing = $this->createFile('copy.txt', 'old');

        $this->fileSystem->copy($source, $existing, true);

        self::assertSame('new', file_get_contents($existing));
    }

    public function testCopyDoesNotOverwrite(): void {
        $source = $this->createFile('source.txt', 'new');
        $existing = $this->createFile('copy.txt', 'old');

        $this->expectException(DestinationFileAlreadyExistsException::class);
        $this->expectExceptionMessage("File already exists: $existing");

        $this->fileSystem->copy($source, $existing, false, false);
    }

    public function testCopyGivesUpAfterTooManyNames(): void {
        $source = $this->createFile('source.txt', 'new');
        $this->createFile('copy.txt', 'old');

        $fileSystem = new class extends FileSystem {
            protected const int MAX_FILE_SUFFIX = 2;
        };

        $this->createFile('copy-1.txt', 'old');
        $this->createFile('copy-2.txt', 'old');

        $this->expectException(DestinationFileAlreadyExistsException::class);
        $this->expectExceptionMessage("File already exists: $this->directory" . DIRECTORY_SEPARATOR . 'copy-2.txt');

        $fileSystem->copy($source, "$this->directory/copy.txt");
    }

    public function testCopyOfMissingFile(): void {
        $this->expectException(CannotFindFileException::class);
        $this->expectExceptionMessage("Cannot find '$this->directory/missing.txt'");

        $this->fileSystem->copy("$this->directory/missing.txt", "$this->directory/copy.txt");
    }

    public function testCopyOfUnreadableFile(): void {
        if (DIRECTORY_SEPARATOR === '\\' || (function_exists('posix_getuid') && posix_getuid() === 0)) {
            self::markTestSkipped('The file permissions do not apply.');
        }

        $source = $this->createFile('source.txt', 'content');
        chmod($source, 0);

        $this->expectException(CannotReadFileException::class);
        $this->expectExceptionMessage("Cannot read '$source'");

        $this->fileSystem->copy($source, "$this->directory/copy.txt");
    }

    public function testCannotCopy(): void {
        $source = $this->createFile('source.txt', 'content');
        mkdir("$this->directory/target");

        try {
            // The destination is a directory: overwritten, but it cannot be.
            $this->fileSystem->copy($source, "$this->directory/target", true);
            self::fail('No exception');
        } catch (CannotCopyException $e) {
            self::assertSame("Cannot copy $source to $this->directory" . DIRECTORY_SEPARATOR . 'target', $e->getMessage());
        }
    }

    public function testRemove(): void {
        $path = $this->createFile('file.txt', 'content');

        $this->fileSystem->remove($path);
        $this->fileSystem->remove("$this->directory/missing.txt");

        self::assertFileDoesNotExist($path);
    }

    public function testCannotRemove(): void {
        mkdir("$this->directory/directory");

        $this->expectException(CannotDeleteFileException::class);
        $this->expectExceptionMessage("Cannot delete '$this->directory/directory'.");

        $this->fileSystem->remove("$this->directory/directory");
    }
}

<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Env;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Runway\Env\Exception\EnvParserException;
use Runway\Env\Parser\EnvParser;
use Runway\Env\Provider\EnvVariablesProvider;
use Runway\Tests\Support\TemporaryDirectory;

class EnvTest extends TestCase {
    use TemporaryDirectory;

    protected mixed $variables;

    protected function setUp(): void {
        $this->createTemporaryDirectory();
        // The variables are read once per process: the ones of the other tests are restored.
        $this->variables = $this->variablesProperty()->getValue();
    }

    protected function tearDown(): void {
        $this->variablesProperty()->setValue(null, $this->variables);
        $this->removeTemporaryDirectory();
    }

    public function testParseString(): void {
        self::assertSame(
            ['DEBUG' => false, 'LEVEL' => 3, 'NAME' => 'app', 'EMPTY' => ''],
            new EnvParser()->parseString("DEBUG=false\nLEVEL=3\nNAME=\"app\"\n; comment\nEMPTY=")
        );
    }

    public function testParseInvalidString(): void {
        try {
            new EnvParser()->parseString("A=\"unclosed\nB=(");
            self::fail('No exception');
        } catch (EnvParserException $e) {
            self::assertSame('Cannot parse ENV string.', $e->getMessage());
            self::assertSame("A=\"unclosed\nB=(", $e->getEnvContent());
        }
    }

    public function testParseFile(): void {
        $path = $this->createFile('.env', "A=1\nB=text\n");

        self::assertSame(['A' => 1, 'B' => 'text'], new EnvParser()->parseFile($path));
    }

    public function testParseInvalidFileDoesNotShowContent(): void {
        $path = $this->createFile('.env', "SECRET=\"password\nB=(");

        try {
            new EnvParser()->parseFile($path);
            self::fail('No exception');
        } catch (EnvParserException $e) {
            self::assertStringStartsWith("Cannot parse ENV file $path: ", $e->getMessage());
            self::assertStringNotContainsString('password', $e->getMessage());
            self::assertSame('', $e->getEnvContent());
        }
    }

    public function testLaterFilesOverrideEarlierOnes(): void {
        $first = $this->createFile('first.env', "A=first\nB=first\n");
        $second = $this->createFile('second.env', "B=second\nC=true\n");
        $this->variablesProperty()->setValue(null, null);

        $provider = new EnvFilesProvider([$first, "$this->directory/missing.env", $second]);

        self::assertSame('first', $provider->getEnvVariable('A'));
        self::assertSame('second', $provider->getEnvVariable('B'));
        self::assertTrue($provider->getEnvVariable('C'));
        self::assertNull($provider->getEnvVariable('D'));
    }

    public function testVariablesAreReadOnce(): void {
        $path = $this->createFile('.env', "A=1\n");
        $this->variablesProperty()->setValue(null, null);
        new EnvFilesProvider([$path]);

        file_put_contents($path, "A=2\n");

        self::assertSame(1, new EnvFilesProvider([$path])->getEnvVariable('A'));
        self::assertSame(1, new EnvVariablesProvider()->getEnvVariable('A'));
    }

    public function testNoFiles(): void {
        $this->variablesProperty()->setValue(null, null);

        self::assertNull(new EnvFilesProvider([])->getEnvVariable('A'));
    }

    public function testVariablesOfPathsProvider(): void {
        // tests/config/test.env.
        self::assertSame(4, new EnvVariablesProvider()->getEnvVariable('LOG_LEVEL'));
        self::assertSame('sqlite::memory:', new EnvVariablesProvider()->getEnvVariable('DSN'));
    }

    protected function variablesProperty(): ReflectionProperty {
        return new ReflectionProperty(EnvVariablesProvider::class, 'variables');
    }
}

final class EnvFilesProvider extends EnvVariablesProvider {
    /**
     * @param string[] $files
     */
    public function __construct(
        private readonly array $files
    ) {
        parent::__construct();
    }

    protected function getEnvFilePaths(): array {
        return $this->files;
    }
}

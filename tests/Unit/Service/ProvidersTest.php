<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Runway\Service\DTO\ConfigDTO;
use Runway\Service\Provider\ConfigDirectoriesProvider;
use Runway\Service\Provider\ConfigProvider;
use Runway\Service\Provider\PathsProvider;
use Runway\Tests\Support\TemporaryDirectory;

/**
 * The paths are static (the bootstrap of the application sets them): they are restored after every test.
 */
class ProvidersTest extends TestCase {
    use TemporaryDirectory;

    /**
     * @var array<string, mixed>
     */
    protected array $paths = [];

    protected function setUp(): void {
        $this->createTemporaryDirectory();

        foreach (['configDirectories', 'envFilePaths', 'modulesDirectory'] as $property) {
            $this->paths[$property] = new ReflectionProperty(PathsProvider::class, $property)->getValue();
        }
    }

    protected function tearDown(): void {
        foreach ($this->paths as $property => $value) {
            new ReflectionProperty(PathsProvider::class, $property)->setValue(null, $value);
        }

        $this->removeTemporaryDirectory();
    }

    public function testPathsAreAddedOnce(): void {
        $paths = PathsProvider::getInstance();
        $configDirectories = $paths->getConfigDirectories();
        $envFiles = $paths->getEnvFilePaths();

        $paths->addConfigDirectory('/new/config');
        $paths->addConfigDirectory('/new/config');
        $paths->addEnvFilePath('/new/.env');
        $paths->addEnvFilePath('/new/.env');

        self::assertSame([...$configDirectories, '/new/config'], $paths->getConfigDirectories());
        self::assertSame([...$envFiles, '/new/.env'], $paths->getEnvFilePaths());
    }

    public function testModulesDirectory(): void {
        $paths = PathsProvider::getInstance();

        // Without MODULE_ROOT.
        $paths->setModulesDirectory('');
        self::assertSame('', $paths->getModulesDirectory());

        $paths->setModulesDirectory('/modules');
        self::assertSame('/modules', $paths->getModulesDirectory());
    }

    public function testConfigDirectoriesWithEnabledModules(): void {
        mkdir("$this->directory/Blog/config", 0777, true);
        mkdir("$this->directory/Shop/config", 0777, true);
        mkdir("$this->directory/Disabled/config", 0777, true);
        mkdir("$this->directory/NoConfig");

        PathsProvider::getInstance()->setModulesDirectory($this->directory);

        $directories = new ModulesStub(' Blog , Shop,,NoConfig,Missing,../Blog,Blog/..,.')->getDirectories();

        self::assertSame(
            [...PathsProvider::getInstance()->getConfigDirectories(), "$this->directory/Blog/config", "$this->directory/Shop/config"],
            $directories
        );
    }

    public function testModulesVariableOfOtherType(): void {
        mkdir("$this->directory/1/config", 0777, true);
        PathsProvider::getInstance()->setModulesDirectory($this->directory);

        self::assertSame([...PathsProvider::getInstance()->getConfigDirectories(), "$this->directory/1/config"], new ModulesStub(1)->getDirectories());
        self::assertSame(PathsProvider::getInstance()->getConfigDirectories(), new ModulesStub(['Blog'])->getDirectories());
    }

    public function testNoModulesWithoutModulesDirectory(): void {
        PathsProvider::getInstance()->setModulesDirectory('');

        self::assertSame(PathsProvider::getInstance()->getConfigDirectories(), new ModulesStub('Blog')->getDirectories());
    }

    public function testModulesOfEnvironment(): void {
        mkdir("$this->directory/config");
        PathsProvider::getInstance()->setModulesDirectory($this->directory);

        // test.env: MODULES is empty, it is not a module with an empty name ("<modules>/config").
        self::assertSame(PathsProvider::getInstance()->getConfigDirectories(), new ConfigDirectoriesProvider()->getDirectories());
    }

    public function testConfigIsParsedOnce(): void {
        $this->createFile('services.yaml', "services:\n  greeter: { class: Greeter }\nparameters:\n  name: value\n");
        $provider = new OwnConfigProvider([$this->directory]);

        $config = $provider->getConfig();

        self::assertSame(['greeter'], array_keys($config->getServiceConfig()));
        self::assertSame('value', $config->getParameter('name'));

        unlink("$this->directory/services.yaml");
        self::assertSame($config, $provider->getConfig());
        self::assertSame($config, new OwnConfigProvider([])->getConfig());
    }
}

/**
 * The given value of MODULES.
 */
final class ModulesStub extends ConfigDirectoriesProvider {
    public function __construct(
        private readonly mixed $modules
    ) {}

    protected function getModulesVariable(): mixed {
        return $this->modules;
    }
}

/**
 * A config provider with its own cache and directories.
 */
final class OwnConfigProvider extends ConfigProvider {
    protected static ?ConfigDTO $config = null;

    /**
     * @param string[] $directories
     */
    public function __construct(array $directories) {
        parent::__construct();

        $this->directoriesProvider = new FixedDirectoriesProvider($directories);
    }
}

final class FixedDirectoriesProvider extends ConfigDirectoriesProvider {
    /**
     * @param string[] $directories
     */
    public function __construct(
        private readonly array $directories
    ) {}

    public function getDirectories(): array {
        return $this->directories;
    }
}

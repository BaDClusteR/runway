<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Module;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Runway\Module\DTO\ModuleDTO;
use Runway\Module\Parser\ModuleConfigParser;
use Runway\Module\Provider\ModuleProvider;
use Runway\Service\Provider\PathsProvider;
use Runway\Tests\Support\EnvStub;
use Runway\Tests\Support\TemporaryDirectory;

class ModuleProviderTest extends TestCase {
    use TemporaryDirectory;

    protected string $modulesDirectory = '';

    protected function setUp(): void {
        $this->createTemporaryDirectory();
        $this->modulesDirectory = PathsProvider::getInstance()->getModulesDirectory();
        PathsProvider::getInstance()->setModulesDirectory($this->directory);

        $this->createFile('Blog/config.yaml', "name: Blog\ndescription: Posts\nversion: 1.2.0\n");
        $this->createFile('Shop/config.yaml', "name: Shop\nversion: 2\n");
        $this->createFile('Broken/config.yaml', "just a string");
        mkdir("$this->directory/NoConfig");
        $this->createFile('file.txt', 'not a module');
    }

    protected function tearDown(): void {
        new ReflectionProperty(PathsProvider::class, 'modulesDirectory')->setValue(null, $this->modulesDirectory);
        $this->removeTemporaryDirectory();
    }

    public function testAllModules(): void {
        $modules = $this->provider(' Shop ,Missing')->getAllModules();

        self::assertSame(['Blog', 'Shop'], $this->names($modules));

        self::assertSame('Blog', $modules[0]->config->name);
        self::assertSame('Posts', $modules[0]->config->description);
        self::assertSame('1.2.0', $modules[0]->config->version);
        self::assertSame("$this->directory/Blog", $modules[0]->rootPath);
        self::assertFalse($modules[0]->isEnabled);

        self::assertSame('', $modules[1]->config->description);
        self::assertSame('2', $modules[1]->config->version);
        self::assertTrue($modules[1]->isEnabled);
    }

    public function testEnabledModules(): void {
        $provider = $this->provider('Blog,Shop');

        self::assertSame(['Blog', 'Shop'], $this->names($provider->getEnabledModules()));
        self::assertSame([], $this->provider(null)->getEnabledModules());
        self::assertSame(['Shop'], $this->names($this->provider('Shop')->getEnabledModules()));
    }

    public function testModule(): void {
        $provider = $this->provider('Shop');

        self::assertSame('Shop', $provider->getModule('Shop')?->systemName);
        self::assertNull($provider->getModule('Missing'));
        self::assertNull($provider->getModule('NoConfig'));
        self::assertTrue($provider->isModuleEnabled('Shop'));
        self::assertFalse($provider->isModuleEnabled('Blog'));
        self::assertFalse($provider->isModuleEnabled('Missing'));
        self::assertTrue($provider->isModuleExists('Blog'));
        self::assertFalse($provider->isModuleExists('Missing'));
    }

    public function testNoModulesDirectory(): void {
        PathsProvider::getInstance()->setModulesDirectory('');
        self::assertCount(0, $this->provider('Blog')->getAllModules());

        PathsProvider::getInstance()->setModulesDirectory("$this->directory/missing");
        self::assertCount(0, $this->provider('Shop')->getAllModules());
        self::assertSame("$this->directory/missing", $this->provider('Blog')->getModulesRootPath());
    }

    public function testConfigParser(): void {
        self::assertNull(new ModuleConfigParser()->parseModuleConfig("$this->directory/NoConfig"));
        self::assertNull(new ModuleConfigParser()->parseModuleConfig("$this->directory/Broken"));
        self::assertSame('Blog', new ModuleConfigParser()->parseModuleConfig("$this->directory/Blog")?->name);
    }

    protected function provider(mixed $modules): ModuleProvider {
        return new ModuleProvider(new EnvStub(['MODULES' => $modules]), new ModuleConfigParser());
    }

    /**
     * @param ModuleDTO[] $modules
     *
     * @return string[]
     */
    protected function names(array $modules): array {
        return array_map(static fn(ModuleDTO $module): string => $module->systemName, $modules);
    }
}

<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Runway\Service\ConfigParser\YamlConfigParser;
use Runway\Service\Converter\ConfigConverter;
use Runway\Service\DTO\ConfigDTO;
use Runway\Service\DTO\ParameterDTO;
use Runway\Service\DTO\ParsedConfigDTO;
use Runway\Service\DTO\ParsedServiceDTO;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\DTO\ServiceDTO;
use Runway\Service\Exception\Route\RouteDeclaredMoreThanOnce;
use Runway\Service\Exception\ServiceDeclaredMoreThanOnceException;
use Runway\Service\Helper\ConfigHelper;
use Runway\Service\Helper\ParsedConfigHelper;
use Runway\Tests\Support\TemporaryDirectory;

/**
 * The YAML files of the directories, merged and converted to the services of the container.
 */
class ConfigParsingTest extends TestCase {
    use TemporaryDirectory;

    protected function setUp(): void {
        $this->createTemporaryDirectory();
    }

    protected function tearDown(): void {
        $this->removeTemporaryDirectory();
    }

    public function testYamlFilesOfDirectoriesAndSubdirectories(): void {
        $this->createFile('a/services.yaml', "services:\n  first: { class: First }\nparameters:\n  name: a\n");
        $this->createFile('a/nested/routes.YAML', "routes:\n  home: { path: /, controller: 'Home::index' }\n");
        $this->createFile('a/ignored.yml', "services:\n  ignored: ~\n");
        $this->createFile('a/empty.yaml', '');
        $this->createFile('b/services.yaml', "services:\n  second: ~\nparameters:\n  name: b\n  other: 1\n");

        $config = new YamlConfigParser()->parseDirectories(["$this->directory/a", "$this->directory/b"]);

        self::assertSame(['first', 'second'], array_keys($config->getParsedServiceConfig()));
        self::assertSame('First', $config->getParsedServiceConfig()['first']->getClass());
        self::assertSame("$this->directory/a/services.yaml", $config->getParsedServiceConfig()['first']->getFilePath());
        self::assertSame(['home'], array_keys($config->getRoutes()));
        self::assertSame("$this->directory/a/nested/routes.YAML", $config->getRoutes()['home']->filePath);
        // The later directory overrides a parameter.
        self::assertSame(['name' => 'b', 'other' => 1], array_map(static fn($parameter): mixed => $parameter->value, $config->getParameters()));
    }

    public function testMissingDirectory(): void {
        $config = new YamlConfigParser()->parseDirectories(["$this->directory/missing"]);

        self::assertSame([], $config->getParsedServiceConfig());
    }

    public function testServiceDeclaredTwice(): void {
        $this->createFile('a.yaml', "services:\n  greeter: ~\n");
        $this->createFile('b/b.yaml', "services:\n  greeter: { class: Other }\n");

        try {
            new YamlConfigParser()->parseDirectories([$this->directory]);
            self::fail('No exception');
        } catch (ServiceDeclaredMoreThanOnceException $e) {
            self::assertSame(
                "Configuration error in $this->directory/b/b.yaml: Service greeter is declared more than once (found in"
                . " $this->directory/b/b.yaml and in $this->directory/a.yaml)",
                $e->getMessage()
            );
            self::assertSame("$this->directory/a.yaml", $e->getSecondDeclaration()->getFilePath());
            self::assertSame('Other', $e->service->getClass());
        }
    }

    public function testRouteDeclaredTwice(): void {
        $this->createFile('a.yaml', "routes:\n  home: { path: /, controller: 'A::b' }\n");
        $this->createFile('b.yaml', "routes:\n  home: { path: /home, controller: 'A::b' }\n");

        $this->expectException(RouteDeclaredMoreThanOnce::class);
        $this->expectExceptionMessage("Configuration error in $this->directory/b.yaml: home declared more than once");

        new YamlConfigParser()->parseDirectories([$this->directory]);
    }

    public function testConverterLinksDecorators(): void {
        $config = new ConfigConverter()->convertFromParsedConfig(new ParsedConfigDTO(
            [
                new ParsedServiceDTO('service', 'Service'),
                new ParsedServiceDTO('decorator', 'Decorator', 'service', filePath: '/a.yaml'),
                new ParsedServiceDTO('outer', 'Outer', 'decorator'),
                new ParsedServiceDTO('orphan', 'Orphan', 'missing'),
            ],
            [$route = new RouteDTO('home', '/', 'A', 'b', 0, '', '')],
            ['name' => $parameter = new ParameterDTO('name', 'value')]
        ));

        $services = $config->getServiceConfig();
        self::assertSame(['service', 'decorator', 'outer', 'orphan'], array_keys($services));
        self::assertSame(['decorator'], $services['service']->getDecorators());
        self::assertSame(['outer'], $services['decorator']->getDecorators());
        self::assertSame('service', $services['decorator']->getAncestorService());
        self::assertSame('service', $services['outer']->getAncestorService());
        self::assertSame('service', $services['service']->getAncestorService());
        // The decorated service is not declared.
        self::assertSame('', $services['orphan']->getAncestorService());
        self::assertSame('Decorator', $services['decorator']->getClass());
        self::assertSame('/a.yaml', $services['decorator']->getFilePath());
        self::assertSame([$route], $config->getRouteConfig());
        self::assertSame(['name' => $parameter], $config->getParametersConfig());
        self::assertSame('value', $config->getParameter('name'));
        self::assertNull($config->getParameter('missing'));
    }

    public function testConverterRejectsServiceDeclaredTwice(): void {
        $this->expectException(ServiceDeclaredMoreThanOnceException::class);

        new ConfigConverter()->convertFromParsedConfig(new ParsedConfigDTO([
            new ParsedServiceDTO('service', 'A'),
            new ParsedServiceDTO('service', 'B'),
        ]));
    }

    public function testConverterStartsAnew(): void {
        $converter = new ConfigConverter();
        $converter->convertFromParsedConfig(new ParsedConfigDTO([new ParsedServiceDTO('first', 'A')]));

        $config = $converter->convertFromParsedConfig(new ParsedConfigDTO([new ParsedServiceDTO('second', 'B')]));

        self::assertSame(['second'], array_keys($config->getServiceConfig()));
    }

    public function testParsedConfigHelperStartsAnew(): void {
        $helper = new ParsedConfigHelper();
        $helper->mergeParsedConfigs(new ParsedConfigDTO([new ParsedServiceDTO('first', 'A')]));

        $config = $helper->mergeParsedConfigs(new ParsedConfigDTO([new ParsedServiceDTO('first', 'B')]));

        self::assertSame('B', $config->getParsedServiceConfig()['first']->getClass());
    }

    public function testConfigHelperMergesServices(): void {
        $helper = new ConfigHelper();

        $config = $helper->mergeConfigs(
            new ConfigDTO([new ServiceDTO('a', 'A')]),
            new ConfigDTO([new ServiceDTO('b', 'B')])
        );

        self::assertSame(['a', 'b'], array_keys($config->getServiceConfig()));
        self::assertSame([], $helper->mergeConfigs()->getServiceConfig());
    }

    public function testConfigHelperRejectsServiceDeclaredTwice(): void {
        $this->expectException(ServiceDeclaredMoreThanOnceException::class);
        $this->expectExceptionMessage('Service a is declared more than once (found in /b.yaml and in /a.yaml)');

        new ConfigHelper()->mergeConfigs(
            new ConfigDTO([new ServiceDTO('a', 'A', filePath: '/a.yaml')]),
            new ConfigDTO([new ServiceDTO('a', 'A', filePath: '/b.yaml')])
        );
    }

    public function testConfigDtoSetters(): void {
        $route = new RouteDTO('home', '/', 'A', 'b', 0, '', '');
        $parameter = new ParameterDTO('name', 'value');
        $config = new ConfigDTO()->setRouteConfig([$route])->setParametersConfig(['name' => $parameter]);

        self::assertSame([$route], $config->getRouteConfig());
        self::assertSame('value', $config->getParameter('name'));

        $parsed = new ParsedConfigDTO()->setRoutes(['home' => $route])->setParameters(['name' => $parameter]);
        self::assertSame(['home' => $route], $parsed->getRoutes());
        self::assertSame(['name' => $parameter], $parsed->getParameters());
    }

    public function testParsedServiceFilePath(): void {
        self::assertSame('/b.yaml', new ParsedServiceDTO('a', 'A', filePath: '/a.yaml')->setFilePath('/b.yaml')->getFilePath());
    }
}

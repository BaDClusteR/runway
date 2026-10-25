<?php

declare(strict_types=1);

namespace Runway\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Runway\Dumper\IDumper;
use Runway\Request\IRequest;
use Runway\Request\IRequestRead;
use Runway\Router\IRouter;
use Runway\Router\Provider\ConfigRouteProvider;
use Runway\Service\Provider\ConfigProvider;
use Runway\Singleton\IKernel;
use Runway\Tests\Support\ContainerState;

/**
 * Every service of the package configs (config/services) can be created: a typo in a class name is found here.
 */
class ContainerConfigTest extends TestCase {
    public function testEveryServiceOfPackageCanBeCreated(): void {
        $container = ContainerState::reset();
        $names = [];

        foreach (new ConfigProvider()->getConfig()->getServiceConfig() as $service) {
            if (str_starts_with($service->getFilePath(), RUNWAY_CONFIG_ROOT) && !$service->getDecoratee()) {
                $names[] = $service->getName();
                $class = $service->getClass();
                $instance = $container->getService($service->getName());

                self::assertIsObject($instance, $service->getName());
                self::assertTrue(is_a($instance, $class), $service->getName());

                if (interface_exists($service->getName())) {
                    self::assertInstanceOf($service->getName(), $instance);
                }
            }
        }

        self::assertContains(IRouter::class, $names);
        self::assertContains(ConfigRouteProvider::class, $names);
    }

    public function testSingletonsAreShared(): void {
        $container = ContainerState::reset();

        self::assertSame($container->getService(IRequest::class), $container->getService(IRequestRead::class));
        self::assertSame($container->getService(IKernel::class), $container->getService(IKernel::class));
        self::assertSame($container->getService(IDumper::class), $container->getService(IDumper::class));
    }

    public function testRunwayVersion(): void {
        self::assertSame('2.0.0', new ConfigProvider()->getConfig()->getParameter('runway_version'));
    }
}

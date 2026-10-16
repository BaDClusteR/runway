<?php

declare(strict_types=1);

namespace Runway\Router\Provider;

use Runway\Service\Provider\ConfigProvider;

/**
 * The routes of the "routes" section of the config files.
 */
class ConfigRouteProvider implements IRouteProvider {
    public function getRoutes(): array {
        return new ConfigProvider()->getConfig()->getRouteConfig();
    }
}

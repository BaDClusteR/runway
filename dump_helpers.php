<?php

declare(strict_types=1);

use Runway\Dumper\IDumper;
use Runway\Singleton\Container;

if (!function_exists("dump")) {
    function dump(mixed ...$vars): void {
        if (!$vars) {
            return;
        }

        /** @var IDumper $dumper */
        $dumper = Container::getInstance()->getService(IDumper::class);

        // As is in the CLI, HTML-escaped inside <pre> in the browser.
        $dumper->export(...$vars);
    }
}

if (!function_exists("dd")) {
    function dd(mixed ...$vars): never {
        dump(...$vars);

        die();
    }
}

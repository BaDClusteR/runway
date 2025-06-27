<?php

declare(strict_types=1);

use Runway\Dumper\IDumper;
use Runway\Singleton\Container;

if (!function_exists("dump")) {
    function dump(): void {
        /** @var IDumper $dumper */
        $dumper = Container::getInstance()->getService(IDumper::class);

        header("Content-Type: text/plain");

        $dumper->export(...func_get_args());
    }
}

if (!function_exists("dd")) {
    function dd(): never {
        dump(...func_get_args());

        die();
    }
}
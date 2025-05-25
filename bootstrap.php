<?php

declare(strict_types=1);

use Runway\Dumper\IDumper;
use Runway\Request\IRequest;
use Runway\Service\Exception\ServiceException;
use Runway\Singleton\Container;

const RUNWAY_ROOT = __DIR__;

if (!defined("PROJECT_ROOT")) {
    die("PROJECT_ROOT is not defined.");
}

const SRC_ROOT = RUNWAY_ROOT . "/src";

const MODULE_ROOT = PROJECT_ROOT . "/modules";

$vendorAutoloadFile = RUNWAY_ROOT . "/vendor/autoload.php";

if (!file_exists($vendorAutoloadFile)) {
    die("$vendorAutoloadFile is not found. Try calling 'composer install' from the project directory.");
}

if (!is_readable($vendorAutoloadFile)) {
    die("$vendorAutoloadFile is not readable. Try checking read permissions.");
}

require_once $vendorAutoloadFile;

spl_autoload_register(
    static function (string $class): void {
        if (str_starts_with($class, "\\")) {
            $class = substr($class, 1);
        }

        if (str_starts_with($class, "Runway\\")) {
            $class = substr($class, 7);
        }

        $filename = SRC_ROOT . "/" . str_replace("\\", "/", $class) . ".php";

        if (file_exists($filename) && is_readable($filename)) {
            include $filename;
        }
    }
);

/**
 * @throws ServiceException
 */
function dump(): void {
    /** @var IRequest $request */
    $request = Container::getInstance()->getService(IRequest::class);

    /** @var IDumper $dumper */
    $dumper = Container::getInstance()->getService(IDumper::class);

    if (!$request->isCLI()) {
        header('Content-Type: text/plain');
    }

    $dumper->export(...func_get_args());
}

/**
 * @throws ServiceException
 */
function dd(): never {
    dump(...func_get_args());

    die();
}
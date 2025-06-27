<?php

declare(strict_types=1);

use Runway\Service\Provider\PathsProvider;

const RUNWAY_ROOT = __DIR__;
const RUNWAY_CONFIG_ROOT = RUNWAY_ROOT . "/config";

$pathsProvider = PathsProvider::getInstance();
$pathsProvider->addConfigDirectory(RUNWAY_CONFIG_ROOT);
$pathsProvider->addEnvFilePath(RUNWAY_ROOT . "/.env");

include_once __DIR__ . "/dump_helpers.php";
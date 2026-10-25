<?php

declare(strict_types=1);

// Only the constants: the config directory and the .env file of the package are the defaults of PathsProvider (no
// class is loaded when the autoloader is included).
const RUNWAY_ROOT = __DIR__;
const RUNWAY_CONFIG_ROOT = RUNWAY_ROOT . "/config";

include_once __DIR__ . "/dump_helpers.php";

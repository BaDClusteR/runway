<?php

/**
 * The front controller of the E2E tests (the router script of "php -S"): the fixture routes of tests/config and the
 * environment of the test (RUNWAY_E2E_ENV_FILE), the logs in RUNWAY_E2E_ROOT.
 */

declare(strict_types=1);

use Runway\Service\Provider\PathsProvider;
use Runway\Singleton\Kernel;

define('PROJECT_ROOT', (string)getenv('RUNWAY_E2E_ROOT'));

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$pathsProvider = PathsProvider::getInstance();
$pathsProvider->addConfigDirectory(dirname(__DIR__) . '/config');
$pathsProvider->addEnvFilePath((string)getenv('RUNWAY_E2E_ENV_FILE'));

Kernel::getInstance()->processRequest();

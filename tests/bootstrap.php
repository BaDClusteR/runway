<?php

declare(strict_types=1);

use Runway\Service\Provider\PathsProvider;

// The logs of the tests go to a temporary folder.
define('PROJECT_ROOT', sys_get_temp_dir() . '/runway-tests');

require_once dirname(__DIR__) . '/vendor/autoload.php';

// The fixture services and routes (see tests/Fixture) for the tests of the container, the router and the events.
PathsProvider::getInstance()->addConfigDirectory(__DIR__ . '/config');

// The last one wins: the .env of the package does not change the tests.
PathsProvider::getInstance()->addEnvFilePath(__DIR__ . '/config/test.env');

<?php

use Runway\Singleton\Kernel;

require_once __DIR__ . "/bootstrap.php";

$kernel = Kernel::getInstance();
$kernel->processRequest();
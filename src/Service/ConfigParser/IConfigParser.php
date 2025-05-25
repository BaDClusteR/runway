<?php

namespace Runway\Service\ConfigParser;

use Runway\Event\Exception\EventException;
use Runway\Service\DTO\ParsedConfigDTO;

interface IConfigParser {
    /**
     * @param string[] $directories
     *
     * @throws EventException
     */
    public function parseDirectories(array $directories): ParsedConfigDTO;
}
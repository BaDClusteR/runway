<?php

namespace Runway\Service\Provider;

use Runway\Service\DTO\ConfigDTO;
use Runway\Service\Exception\ServiceDeclaredMoreThanOnceException;

interface IConfigProvider {
    /**
     * @throws ServiceDeclaredMoreThanOnceException
     */
    public function getConfig(): ConfigDTO;
}
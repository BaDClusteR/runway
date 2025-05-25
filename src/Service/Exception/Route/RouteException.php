<?php

declare(strict_types=1);

namespace Runway\Service\Exception\Route;

use Runway\Service\DTO\RouteDTO;
use Runway\Service\Exception\ConfigurationException;
use Throwable;

class RouteException extends ConfigurationException {
    public function __construct(
        public readonly RouteDTO $route,
        string                   $message,
        int                      $code = 0,
        ?Throwable               $previous = null
    ) {
        parent::__construct($this->route->filePath, $message, $code, $previous);
    }
}
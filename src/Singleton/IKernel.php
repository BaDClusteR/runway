<?php

namespace Runway\Singleton;

use Runway\ISingleton;
use Runway\Request\IResponseRead;

interface IKernel extends ISingleton {
    public function processRequest(): void;

    public function processResponse(IResponseRead $response): void;

    public function isDebugMode(): bool;
}
<?php

declare(strict_types=1);

namespace Runway\DataStorage\Exception;

use Throwable;

class DBNotConnectedException extends DBException {
    public function __construct(
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("Not connected to a database", $code, $previous);
    }
}
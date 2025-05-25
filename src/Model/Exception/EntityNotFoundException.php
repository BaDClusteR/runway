<?php

declare(strict_types=1);

namespace Runway\Model\Exception;


use Throwable;

class EntityNotFoundException extends ModelException {
    public function __construct(
        string     $fqn,
        int        $id,
        int        $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct("$fqn with ID $id not found in the data storage.", $code, $previous);
    }
}
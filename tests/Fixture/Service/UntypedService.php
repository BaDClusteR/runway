<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

final class UntypedService {
    public mixed $value;

    /**
     * @param mixed $value
     */
    public function __construct($value) {
        $this->value = $value;
    }
}

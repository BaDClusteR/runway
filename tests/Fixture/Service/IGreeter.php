<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

interface IGreeter {
    public function greet(string $name): string;
}

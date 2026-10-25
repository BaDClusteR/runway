<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

final class Greeter implements IGreeter {
    public function greet(string $name): string {
        return "Hello, $name";
    }
}

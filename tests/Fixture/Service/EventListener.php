<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

/**
 * Records the calls of its event methods (a new instance is created for every call).
 */
final class EventListener {
    /**
     * @var list<array{string, mixed}>
     */
    public static array $calls = [];

    public function onFixtureEvent(mixed $envelope): void {
        self::$calls[] = ['onFixtureEvent', $envelope];
    }

    public function onFixtureEventAgain(mixed $envelope): void {
        self::$calls[] = ['onFixtureEventAgain', $envelope];
    }
}

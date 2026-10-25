<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Runway\Event\EventDispatcher;
use Runway\Tests\Fixture\Service\EventListener;

/**
 * The listeners of tests/config/services.yaml.
 */
class EventDispatcherTest extends TestCase {
    protected function setUp(): void {
        EventListener::$calls = [];
    }

    public function testListenersAreCalledInDeclarationOrder(): void {
        $envelope = new \stdClass();

        new EventDispatcher()->dispatch('fixture.event', $envelope);

        self::assertSame([['onFixtureEvent', $envelope], ['onFixtureEventAgain', $envelope]], EventListener::$calls);
    }

    public function testEventWithoutListeners(): void {
        new EventDispatcher()->dispatch('fixture.unknown', 'envelope');

        self::assertSame([], EventListener::$calls);
    }
}

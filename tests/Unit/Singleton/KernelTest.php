<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Singleton;

use PHPUnit\Framework\TestCase;
use Runway\Request\IRequestRead;
use Runway\Request\Response;
use Runway\Singleton\Kernel;
use Runway\Tests\Support\EnvStub;
use Runway\Tests\Support\EventDispatcherSpy;

/**
 * The CLI has no headers: the status, the headers and the cookies are in the E2E tests.
 */
class KernelTest extends TestCase {
    public function testRequestIsProcessedAfterInit(): void {
        $events = new EventDispatcherSpy();
        $request = $this->createMock(IRequestRead::class);
        $request->expects($this->once())
                ->method('process')
                ->willReturnCallback(static function () use ($events): Response {
                    // The listeners of kernel.init run before the request.
                    self::assertSame([['kernel.init', null]], $events->events);

                    return new Response(200, 'body');
                });

        $this->expectOutputString('body');

        new Kernel($request, $events, new EnvStub())->processRequest();
    }

    public function testBodyIsSent(): void {
        $this->expectOutputString('the body');

        $this->kernel()->processResponse(new Response(201, 'the body', ['X-Test' => 'yes']));
    }

    public function testDebugModeIsTrueOnly(): void {
        self::assertTrue($this->kernel(['APP_DEBUG' => true])->isDebugMode());
        self::assertFalse($this->kernel(['APP_DEBUG' => 'true'])->isDebugMode());
        self::assertFalse($this->kernel(['APP_DEBUG' => 1])->isDebugMode());
        self::assertFalse($this->kernel()->isDebugMode());
    }

    /**
     * @param array<string, mixed> $env
     */
    protected function kernel(array $env = []): Kernel {
        return new Kernel($this->createStub(IRequestRead::class), new EventDispatcherSpy(), new EnvStub($env));
    }
}

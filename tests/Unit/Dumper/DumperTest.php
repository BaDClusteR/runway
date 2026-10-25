<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Dumper;

use PHPUnit\Framework\TestCase;
use Runway\Dumper\Dumper;
use Runway\Request\IRequestRead;

class DumperTest extends TestCase {
    public function testExportInCli(): void {
        $this->expectOutputString("'<b>'\n\narray (\n  0 => 1,\n)");

        $this->dumper(true)->export('<b>', [1]);
    }

    public function testExportInBrowserIsEscaped(): void {
        $this->expectOutputString('<pre>&#039;&lt;script&gt;&quot;&#039;</pre>');

        $this->dumper(false)->export('<script>"');
    }

    public function testDump(): void {
        $this->expectOutputString("<pre>string(3) &quot;&lt;b&gt;&quot;\nint(1)\n</pre>");

        $this->dumper(false)->dump('<b>', 1);
    }

    public function testDumpInCli(): void {
        $this->expectOutputString("string(3) \"<b>\"\n");

        $this->dumper(true)->dump('<b>');
    }

    public function testPrint(): void {
        $this->expectOutputString("<pre>&lt;b&gt;\nArray\n(\n    [0] =&gt; 1\n)\n</pre>");

        $this->dumper(false)->print('<b>', [1]);
    }

    public function testJson(): void {
        $this->expectOutputString("{\"a\":\"<b>\"}\n\n[Cannot encode variable to JSON]");

        $this->dumper(true)->json(['a' => '<b>'], NAN);
    }

    public function testRequestIsAskedOnce(): void {
        $request = $this->createMock(IRequestRead::class);
        $request->expects($this->once())->method('isCLI')->willReturn(true);
        $dumper = new Dumper($request);

        $this->expectOutputString('11');

        $dumper->export(1);
        $dumper->export(1);
    }

    protected function dumper(bool $isCli): Dumper {
        $request = $this->createStub(IRequestRead::class);
        $request->method('isCLI')->willReturn($isCli);

        return new Dumper($request);
    }
}

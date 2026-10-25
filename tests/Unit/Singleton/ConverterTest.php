<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Singleton;

use DateTime;
use PHPUnit\Framework\TestCase;
use Runway\Singleton\Converter;
use Runway\Tests\Support\LoggerSpy;

class ConverterTest extends TestCase {
    protected LoggerSpy $logger;

    protected Converter $converter;

    protected function setUp(): void {
        $this->logger = new LoggerSpy();
        $this->converter = new Converter($this->logger);
    }

    public function testCapitalize(): void {
        self::assertSame('CreatedAt', $this->converter->capitalize('createdAt'));
        self::assertSame('CreatedAt', $this->converter->capitalize('CreatedAt'));
        self::assertSame('', $this->converter->capitalize(''));
    }

    public function testDeCapitalize(): void {
        self::assertSame('createdAt', $this->converter->deCapitalize('CreatedAt'));
        self::assertSame('createdAt', $this->converter->deCapitalize('createdAt'));
        self::assertSame('', $this->converter->deCapitalize(''));
    }

    public function testDateTimeToTimestamp(): void {
        self::assertSame(1700000000, $this->converter->dateTimeToTimestamp(1700000000));
        self::assertSame(1700000000, $this->converter->dateTimeToTimestamp('1700000000'));
        self::assertSame(1700000000, $this->converter->dateTimeToTimestamp(new DateTime('@1700000000')));
        self::assertSame(1700000000, $this->converter->dateTimeToTimestamp('2023-11-14T22:13:20+00:00'));
        self::assertSame([], $this->logger->records);
    }

    public function testMalformedDateTimeIsMinusOne(): void {
        self::assertSame(-1, $this->converter->dateTimeToTimestamp('not a date'));
        self::assertSame(
            ['warning: Runway\Singleton\Converter::dateTimeToTimestamp: malformed date-time string (not a date)'],
            $this->logger->getMessages()
        );
    }

    public function testTimestampToDateTime(): void {
        self::assertSame(1700000000, $this->converter->timestampToDateTime(1700000000)?->getTimestamp());
    }

    public function testDateTimeToString(): void {
        $dateTime = new DateTime('2023-11-14T22:13:20+03:00');

        self::assertSame('2023-11-14T22:13:20+03:00', $this->converter->dateTimeToString($dateTime));
        self::assertSame(date(DATE_ATOM, 1700000000), $this->converter->dateTimeToString(1700000000));
        self::assertSame(date(DATE_ATOM, 1700000000), $this->converter->dateTimeToString('1700000000'));
        self::assertSame('2023-11-14T22:13:20+03:00', $this->converter->dateTimeToString('2023-11-14 22:13:20+03:00'));
        self::assertSame([], $this->logger->records);
    }

    public function testMalformedDateTimeStringIsEmpty(): void {
        self::assertSame('', $this->converter->dateTimeToString('not a date'));
        self::assertCount(1, $this->logger->records);
        self::assertStringStartsWith(
            'Runway\Singleton\Converter::dateTimeToString: malformed date-time string (',
            $this->logger->records[0]['message']
        );
        self::assertSame('warning', $this->logger->records[0]['level']);
    }
}

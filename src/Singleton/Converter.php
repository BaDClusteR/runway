<?php

declare(strict_types=1);

namespace Runway\Singleton;

use Runway\Logger\ILogger;
use Runway\Singleton;
use DateMalformedStringException;
use DateTime;
use Exception;

class Converter extends Singleton implements IConverter {
    public function __construct(
        protected ILogger $logger
    ) {}

    public const string DATE_TIME_FORMAT = DATE_ATOM;

    public function capitalize(string $str): string {
        return ucfirst($str);
    }

    public function deCapitalize(string $str): string {
        return lcfirst($str);
    }

    public function dateTimeToTimestamp(DateTime|string|int $dateTime): int {
        if ($dateTime instanceof DateTime) {
            return $dateTime->getTimestamp();
        }

        if (is_numeric($dateTime)) {
            return (int)$dateTime;
        }

        $result = strtotime($dateTime);

        // -1 for a malformed string (as before), not a TypeError.
        if ($result === false) {
            $this->logger->warning(__METHOD__ . ": malformed date-time string ($dateTime)");

            return -1;
        }

        return $result;
    }

    public function timestampToDateTime(int $timestamp): ?DateTime {
        try {
            return new DateTime(
                date(
                    static::DATE_TIME_FORMAT,
                    $timestamp
                )
            );
        } catch (Exception) {
            return null;
        }
    }

    public function dateTimeToString(DateTime|int|string $dateTime): string {
        if ($dateTime instanceof DateTime) {
            return $dateTime->format(
                static::DATE_TIME_FORMAT
            );
        }

        if (is_numeric($dateTime)) {
            return date(
                static::DATE_TIME_FORMAT,
                (int)$dateTime
            );
        }

        try {
            return new DateTime($dateTime)->format(
                static::DATE_TIME_FORMAT
            );
        } catch (DateMalformedStringException $e) {
            $this->logger->warning(
                __METHOD__ . ": malformed date-time string ({$e->getMessage()})"
            );

            return "";
        }
    }
}
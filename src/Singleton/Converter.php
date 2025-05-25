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

    protected const string DATE_TIME_FORMAT = "Y-m-d H:i:s";

    public function capitalize(string $str): string {
        return strtoupper($str[0]) . substr($str, 1);
    }

    public function deCapitalize(string $str): string {
        return strtolower($str[0]) . substr($str, 1);
    }

    public function dateTimeToTimestamp(DateTime|string|int $dateTime): int {
        $result = -1;

        if (is_numeric($dateTime)) {
            $result = (int)$dateTime;
        } elseif (is_string($dateTime)) {
            $result = strtotime($dateTime);
        } elseif ($dateTime instanceof DateTime) {
            $result = $dateTime->getTimestamp();
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
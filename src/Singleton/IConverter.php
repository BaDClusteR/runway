<?php

namespace Runway\Singleton;

use DateTime;

interface IConverter {
    public function capitalize(string $str): string;

    public function deCapitalize(string $str): string;

    public function dateTimeToTimestamp(DateTime|string|int $dateTime): int;

    public function timestampToDateTime(int $timestamp): ?DateTime;

    public function dateTimeToString(DateTime|string|int $dateTime): string;
}
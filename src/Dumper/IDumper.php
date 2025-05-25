<?php

namespace Runway\Dumper;

interface IDumper {
    public function dump($var, ...$vars): void;

    public function print($var, ...$vars): void;

    public function json($var, ...$vars): void;

    public function export($var, ...$vars): void;
}
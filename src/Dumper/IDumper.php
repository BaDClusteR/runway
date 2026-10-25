<?php

namespace Runway\Dumper;

interface IDumper {
    public function dump(mixed $var, mixed ...$vars): void;

    public function print(mixed $var, mixed ...$vars): void;

    public function json(mixed $var, mixed ...$vars): void;

    public function export(mixed $var, mixed ...$vars): void;
}

<?php

namespace Runway;

interface ISingleton {
    public static function getInstance(): static;

    public static function resetInstance(): static;
}
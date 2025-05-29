<?php

namespace Runway\EventListener;

class Kernel
{
    public function onInit(): void {
        define("RUNWAY_ROOT", __DIR__);
        define("RUNWAY_CONFIG_ROOT", __DIR__ . "/config");
    }
}
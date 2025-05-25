<?php

namespace Runway\Controller;

use Runway\Request\Response;

interface IController404 {
    public function run(): Response;
}
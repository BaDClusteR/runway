<?php

declare(strict_types=1);

namespace Runway\Controller;

use Runway\Request\Response;

class Controller404 implements IController404 {
    public function run(): Response {
        return new Response(
            404,
            '404 error'
        );
    }
}
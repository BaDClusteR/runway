<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Controller;

use Runway\Request\Response;

/**
 * A controller that is not a service: the router creates it.
 */
class PlainController {
    public function index(): Response {
        return new Response(200, 'plain');
    }
}

<?php

declare(strict_types=1);

namespace Runway\Controller;

use Runway\Request\IRequestRead;
use Runway\Request\Response;

/**
 * "405 Method Not Allowed" with the allowed methods. An OPTIONS request (unless a route handles it) gets the allowed
 * methods as a successful response.
 */
class MethodNotAllowedController implements IMethodNotAllowedController {
    public function __construct(
        protected IRequestRead $request
    ) {}

    public function run(array $allowedMethods): Response {
        $allow = implode(', ', $this->getAllowHeaderMethods($allowedMethods));

        return (strtoupper($this->request->getMethod()) === 'OPTIONS')
            ? new Response(204, '', ['Allow' => $allow])
            : new Response(405, '405 error', ['Allow' => $allow]);
    }

    /**
     * @param string[] $allowedMethods
     *
     * @return string[]
     */
    protected function getAllowHeaderMethods(array $allowedMethods): array {
        return array_values(array_unique([...$allowedMethods, 'OPTIONS']));
    }
}

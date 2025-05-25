<?php

namespace Runway\Controller\FrontPage;

use Runway\Request\IResponse;
use Runway\Request\Response;

class FrontPageController
{
    public function __construct(
        protected IResponse $response,
        protected string $version
    ) {
    }

    public function showFrontPage(): Response {
        return $this->response
            ->setCode(200)
            ->setBody("Runway v.{$this->version}<br>Here will be a front page. ");
    }
}
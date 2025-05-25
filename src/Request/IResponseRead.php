<?php

namespace Runway\Request;

use Runway\Request\Parameters\DTO\CookieDTO;

interface IResponseRead {
    public function getCode(): int;

    public function getBody(): string;

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * @return CookieDTO[]
     */
    public function getCookies(): array;
}
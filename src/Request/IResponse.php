<?php

namespace Runway\Request;

use Runway\Request\Parameters\DTO\CookieDTO;

interface IResponse extends IResponseRead {
    public function setBody(string $body): static;

    /**
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): static;

    public function addHeader(string $name, string $value): static;

    /**
     * @param array<string, string> $headers
     */
    public function addHeaders(array $headers): static;

    public function setCode(int $code): static;

    /**
     * @param CookieDTO[] $cookies
     */
    public function setCookies(array $cookies): static;

    public function addCookie(CookieDTO $cookie): static;
}
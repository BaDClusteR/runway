<?php

namespace Runway\Request;

use Runway\Request\Parameters\DTO\FileDTO;
use Runway\Request\Parameters\IRequestParameterValue;

interface IRequestRead {
    public function getProtocol(): string;

    public function getHost(): string;

    public function getPort(): int;

    public function getPath(): string;

    public function getRequestUri(): string;

    public function getIpAddress(): string;

    public function getMethod(): string;

    public function hasGetParameter(string $parameterName): bool;

    public function getGetParameter(string $parameterName): IRequestParameterValue;

    public function hasPostParameter(string $parameterName): bool;

    public function getPostParameter(string $parameterName): IRequestParameterValue;

    public function getServerParameter(string $parameterName): IRequestParameterValue;

    public function getServerParameters(): array;

    public function getHeader(string $headerName): ?string;

    public function getCookie(string $cookieName): ?string;

    public function getBody(): string;

    /**
     * @return string[]
     */
    public function getArguments(): array;

    public function getFile(string $fileIndex): ?FileDTO;

    /**
     * @return FileDTO[]
     */
    public function getFiles(): array;

    public function getRequestId(): string;

    public function process(): IResponseRead;

    public function getUserAgent(): string;

    public function isCLI(): bool;
}
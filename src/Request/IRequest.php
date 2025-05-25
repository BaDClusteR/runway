<?php

namespace Runway\Request;

use Runway\Request\Parameters\DTO\FileDTO;

interface IRequest extends IRequestRead {
    public function setProtocol(string $protocol): static;

    public function setHost(string $host): static;

    public function setPort(int $port): static;

    public function setPath(string $path): static;

    public function setRequestUri(string $requestUri): static;

    public function setIpAddress(string $ipAddress): static;

    public function setMethod(string $method): static;

    /**
     * @param array<string, mixed> $getParameters
     */
    public function setGetParameters(array $getParameters): static;

    /**
     * @param array<string, mixed> $postParameters
     */
    public function setPostParameters(array $postParameters): static;

    /**
     * @param array<string, mixed> $serverParameters
     */
    public function setServerParameters(array $serverParameters): static;

    /**
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): static;

    /**
     * @param array<string, string> $cookies
     */
    public function setCookies(array $cookies): static;

    /**
     * @param FileDTO[] $files
     */
    public function setFiles(array $files): static;

    public function setBody(string $body): static;

    /**
     * @param string[] $arguments
     */
    public function setArguments(array $arguments): static;

    public function setRequestId(string $requestId): static;
}
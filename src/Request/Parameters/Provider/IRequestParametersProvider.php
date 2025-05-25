<?php

namespace Runway\Request\Parameters\Provider;

use Runway\Request\Parameters\DTO\FileDTO;

interface IRequestParametersProvider {
    public function getMethod(): string;

    public function getHost(): string;

    public function getPort(): int;

    public function getIpAddress(): string;

    public function getProtocol(): string;

    public function getPath(): string;

    public function getRequestUri(): string;

    public function getBody(): string;

    /**
     * @return array<string, mixed>
     */
    public function getGetParameters(): array;

    /**
     * @return array<string, mixed>
     */
    public function getPostParameters(): array;

    /**
     * @return array<string, mixed>
     */
    public function getServerParameters(): array;

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * @return array<string, string>
     */
    public function getCookies(): array;

    /**
     * @return string[]
     */
    public function getArguments(): array;

    /**
     * @return FileDTO[]
     */
    public function getFiles(): array;
}
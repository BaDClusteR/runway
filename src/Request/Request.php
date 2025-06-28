<?php

declare(strict_types=1);

namespace Runway\Request;

use Runway\Controller\IExceptionController;
use Runway\Exception\FatalErrorException;
use Runway\Logger\ILogger;
use Runway\Request\Parameters\DTO\FileDTO;
use Runway\Request\Parameters\IRequestParameterValue;
use Runway\Request\Parameters\Provider\IRequestParametersProvider;
use Runway\Request\Parameters\RequestParameterValue;
use Runway\Router\IRouter;
use Runway\Singleton;
use Runway\Singleton\Container;
use Ramsey\Uuid\Uuid;
use Throwable;

class Request extends Singleton implements IRequest {
    protected string $protocol = '';

    protected string $host = '';

    protected int $port = 0;

    protected string $path = '';

    protected string $requestUri = '';

    protected string $ipAddress = '';

    protected string $method = '';

    protected string $body = '';

    protected string $requestId = '';

    /**
     * @var array<string, mixed> $getParameters
     */
    protected array $getParameters = [];

    /**
     * @var array<string, mixed> $postParameters
     */
    protected array $postParameters = [];

    /**
     * @var array<string, mixed> $serverParameters
     */
    protected array $serverParameters = [];

    /**
     * @var array<string, string> $headers
     */
    protected array $headers = [];

    /**
     * @var array<string, string> $cookies
     */
    protected array $cookies = [];

    /**
     * @var FileDTO[] $files
     */
    protected array $files = [];

    /**
     * @var array<string, string>
     */
    protected array $arguments = [];

    public function __construct(
        protected IRequestParametersProvider $requestParametersProvider,
        protected ILogger                    $logger
    ) {
        $this->initParameters();
    }

    protected function initParameters(): void {
        $this->body = $this->requestParametersProvider->getBody();
        $this->headers = $this->requestParametersProvider->getHeaders();
        $this->arguments = $this->requestParametersProvider->getArguments();
        $this->files = $this->requestParametersProvider->getFiles();
        $this->cookies = $this->requestParametersProvider->getCookies();
        $this->method = $this->requestParametersProvider->getMethod();
        $this->path = $this->requestParametersProvider->getPath();
        $this->requestUri = $this->requestParametersProvider->getRequestUri();
        $this->getParameters = $this->requestParametersProvider->getGetParameters();
        $this->postParameters = $this->requestParametersProvider->getPostParameters();
        $this->serverParameters = $this->requestParametersProvider->getServerParameters();
        $this->host = $this->requestParametersProvider->getHost();
        $this->protocol = $this->requestParametersProvider->getProtocol();
        $this->ipAddress = $this->requestParametersProvider->getIpAddress();
        $this->port = $this->requestParametersProvider->getPort();
        $this->requestId = Uuid::uuid4()->toString();
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function setMethod(string $method): static {
        $this->method = $method;

        return $this;
    }

    public function hasGetParameter(string $parameterName): bool {
        return array_key_exists($parameterName, $this->getParameters);
    }

    public function getGetParameter(string $parameterName): IRequestParameterValue {
        return new RequestParameterValue(
            $this->getParameters[$parameterName] ?? null
        );
    }

    /**
     * @param array<string, string> $getParameters
     */
    public function setGetParameters(array $getParameters): static {
        $this->getParameters = $getParameters;

        return $this;
    }

    public function hasPostParameter(string $parameterName): bool {
        return array_key_exists($parameterName, $this->postParameters);
    }

    public function getPostParameter(string $parameterName): IRequestParameterValue {
        return new RequestParameterValue(
            $this->postParameters[$parameterName] ?? null
        );
    }

    /**
     * @param array<string, string> $postParameters
     */
    public function setPostParameters(array $postParameters): static {
        $this->postParameters = $postParameters;

        return $this;
    }

    public function getServerParameter(string $parameterName): IRequestParameterValue {
        return new RequestParameterValue(
            $this->serverParameters[$parameterName] ?? null
        );
    }

    public function getServerParameters(): array {
        return $this->serverParameters;
    }

    public function setServerParameters(array $serverParameters): static {
        $this->serverParameters = $serverParameters;

        return $this;
    }

    public function getHeader(string $headerName): ?string {
        $headerName = strtolower($headerName);

        return empty($this->headers[$headerName])
            ? null
            : $this->headers[$headerName];
    }

    /**
     * @param array<string, string> $headers
     */
    public function setHeaders(array $headers): static {
        foreach ($headers as $headerName => $headerValue) {
            $this->headers[strtolower($headerName)] = $headerValue;
        }

        return $this;
    }

    public function getCookie(string $cookieName): ?string {
        return empty($this->cookies[$cookieName])
            ? null
            : $this->cookies[$cookieName];
    }

    /**
     * @param array<string, string> $cookies
     */
    public function setCookies(array $cookies): static {
        $this->cookies = $cookies;

        return $this;
    }

    public function getBody(): string {
        return $this->body;
    }

    public function setBody(string $body): static {
        $this->body = $body;

        return $this;
    }

    public function getProtocol(): string {
        return $this->protocol;
    }

    public function setProtocol(string $protocol): static {
        $this->protocol = $protocol;

        return $this;
    }

    public function getHost(): string {
        return $this->host;
    }

    public function setHost(string $host): static {
        $this->host = $host;

        return $this;
    }

    public function getPort(): int {
        return $this->port;
    }

    public function setPort(int $port): static {
        $this->port = $port;

        return $this;
    }

    public function getPath(): string {
        return $this->path;
    }

    public function setPath(string $path): static {
        $this->path = $path;

        return $this;
    }

    public function getRequestUri(): string {
        return $this->requestUri;
    }

    public function setRequestUri(string $requestUri): static {
        $this->requestUri = $requestUri;

        return $this;
    }

    public function getIpAddress(): string {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getArguments(): array {
        return $this->arguments;
    }

    /**
     * @param string[] $arguments
     */
    public function setArguments(array $arguments): static {
        $this->arguments = $arguments;

        return $this;
    }

    public function process(): IResponseRead {
        $this->setErrorHandler();

        try {
            /** @var IRouter $router */
            $router = Container::getInstance()->getService(IRouter::class);

            return $router->process(
                $this->getPath(),
            );
        } catch (Throwable $e) {
            /** @var IExceptionController $exceptionController */
            $exceptionController = Container::getInstance()->getService(IExceptionController::class);

            return $exceptionController->run($e);
        }
    }

    protected function setErrorHandler(): void {
        set_error_handler(
            $this->getErrorHandler()
        );
    }

    /**
     * @return callable(int $errno, string $errStr, string $errFile, int $errLine): never
     */
    protected function getErrorHandler(): callable {
        return function (
            int    $errno,
            string $errStr,
            string $errFile,
            int    $errLine
        ): bool {
            $this->logger->error($errStr);

            if ($this->isFatalError($errno)) {
                throw new FatalErrorException(
                    $errno,
                    $errStr,
                    $errFile,
                    $errLine
                );
            }

            return false;
        };
    }

    protected function isFatalError(int $errno): bool {
        if (
            array_any(
                $this->getFataErrorTypes(),
                static fn(int $type): bool => (bool)($errno & $type)
            )
        ) {
            return true;
        }

        return false;
    }

    /**
     * @return int[]
     */
    protected function getFataErrorTypes(): array {
        return [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_USER_ERROR,
            E_RECOVERABLE_ERROR
        ];
    }

    /**
     * @param FileDTO[] $files
     */
    public function setFiles(array $files): static {
        $this->files = $files;

        return $this;
    }

    public function getFile(string $fileIndex): ?FileDTO {
        return $this->files[$fileIndex] ?? null;
    }

    public function getFiles(): array {
        return $this->files;
    }

    public function isCLI(): bool {
        return PHP_SAPI === 'cli';
    }

    public function getRequestId(): string {
        return $this->requestId;
    }

    public function setRequestId(string $requestId): static {
        $this->requestId = $requestId;

        return $this;
    }

    public function getUserAgent(): string {
        return (string)$this->getHeader('user-agent');
    }
}
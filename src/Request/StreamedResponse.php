<?php

declare(strict_types=1);

namespace Runway\Request;

use Runway\Exception\RuntimeException;

/**
 * A response whose body is made by the callback while it's being sent (see IStreamedResponse). The callback gets the
 * function that sends a piece of the body: every piece goes to the client at once.
 *
 *     return new StreamedResponse(200, function (callable $send): void {
 *         foreach ($rows as $row) {
 *             $send(implode(',', $row) . "\n");
 *         }
 *     }, ['Content-Type' => 'text/csv']);
 *
 * There is no Content-Length unless it's given (e.g. the size is known in advance, so the browser shows the
 * progress): the web server sends the body in chunks then.
 */
class StreamedResponse extends Response implements IStreamedResponse {
    /** @var callable(callable(string $data): void $send): void */
    protected $callback;

    protected bool $isSent = false;

    /**
     * @param callable(callable(string $data): void $send): void $callback
     * @param array<string, string>                              $headers
     * @param array<string, string>                              $cookies
     */
    public function __construct(int $code, callable $callback, array $headers = [], array $cookies = []) {
        parent::__construct($code, '', $headers, $cookies);
        $this->callback = $callback;
    }

    /**
     * @throws RuntimeException The body is sent already.
     */
    public function sendBody(): void {
        if ($this->isSent) {
            throw new RuntimeException('The body of the response is sent already.');
        }

        $this->isSent = true;

        ($this->callback)(static function (string $data): void {
            echo $data;
            flush();
        });
    }

    protected function addDefaultContentLengthIfNeeded(array $headers): array {
        return $headers;
    }
}

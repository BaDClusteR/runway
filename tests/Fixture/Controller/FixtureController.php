<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Controller;

use Runway\Request\IRequestRead;
use Runway\Request\Parameters\DTO\CookieDTO;
use Runway\Request\Response;
use Runway\Request\StreamedResponse;
use RuntimeException;

/**
 * The controller of the fixture routes (tests/config/routes.yaml).
 */
class FixtureController {
    public function __construct(
        protected IRequestRead $request
    ) {}

    public function hello(): Response {
        return new Response(200, 'Hello', ['Content-Type' => 'text/plain']);
    }

    public function item(string $id): Response {
        return $this->json(['id' => $id, 'method' => $this->request->getMethod()]);
    }

    public function search(): Response {
        return new Response(200, 'search');
    }

    /**
     * The request as the controller sees it.
     */
    public function echo(): Response {
        $files = [];

        foreach ($this->request->getFiles() as $field => $file) {
            $files[$field] = ['name' => $file->getName(), 'size' => $file->getSize(), 'content' => $file->getContent()];
        }

        return $this->json([
            'method'  => $this->request->getMethod(),
            'path'    => $this->request->getPath(),
            'query'   => $this->request->getGetParameter('q')->asString(),
            'post'    => $this->request->getPostParameter('name')->asString(),
            'header'  => $this->request->getHeader('X-Test'),
            'cookie'  => $this->request->getCookie('session'),
            'ip'      => $this->request->getIpAddress(),
            'body'    => $this->request->getBody(),
            'files'   => $files,
            'route'   => $this->request->getRouteMatch()?->route->name,
        ]);
    }

    public function cookie(): Response {
        return new Response(204, '', [], [
            new CookieDTO('session', 'abc', 0, '/', '', false, true, 'Strict'),
        ]);
    }

    public function stream(): StreamedResponse {
        return new StreamedResponse(200, static function (callable $send): void {
            foreach (['first,', 'second,', 'third'] as $piece) {
                $send($piece);
            }
        }, ['Content-Type' => 'text/csv']);
    }

    public function exception(): Response {
        throw new RuntimeException('SELECT secret FROM internals');
    }

    public function notResponse(): string {
        return 'not a response';
    }

    public function file(string $path): Response {
        return new Response(200, $path);
    }

    protected function secret(): Response {
        return new Response(200, 'secret');
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function json(array $data): Response {
        return new Response(200, (string)json_encode($data), ['Content-Type' => 'application/json']);
    }
}

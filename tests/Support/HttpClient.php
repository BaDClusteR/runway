<?php

declare(strict_types=1);

namespace Runway\Tests\Support;

/**
 * HTTP requests with the streams of PHP: no extensions are needed.
 */
final readonly class HttpClient {
    public function __construct(
        private string $baseUrl
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public function request(string $method, string $path, string $body = '', array $headers = []): HttpResponse {
        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }

        $context = stream_context_create([
            'http' => [
                'method'          => $method,
                'header'          => implode("\r\n", $headerLines),
                'content'         => $body,
                // The errors are responses too.
                'ignore_errors'   => true,
                'follow_location' => 0,
                'timeout'         => 10,
            ],
        ]);

        $responseBody = (string)file_get_contents($this->baseUrl . $path, false, $context);

        return $this->parseResponse(http_get_last_response_headers() ?? [], $responseBody);
    }

    /**
     * A "multipart/form-data" request: the fields and the files (field name => [filename, content]).
     *
     * @param array<string, string>                      $fields
     * @param list<array{string, string, string}>        $files  Field name, file name, content.
     * @param array<string, string>                      $headers
     */
    public function postForm(string $path, array $fields, array $files, array $headers = []): HttpResponse {
        $boundary = 'boundary' . bin2hex(random_bytes(8));
        $body = '';

        foreach ($fields as $name => $value) {
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
        }

        foreach ($files as [$name, $filename, $content]) {
            $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"; filename=\"$filename\"\r\n"
                . "Content-Type: application/octet-stream\r\n\r\n$content\r\n";
        }

        $body .= "--$boundary--\r\n";

        return $this->request('POST', $path, $body, ['Content-Type' => "multipart/form-data; boundary=$boundary", ...$headers]);
    }

    /**
     * @param string[] $headerLines
     */
    private function parseResponse(array $headerLines, string $body): HttpResponse {
        $code = 0;
        $headers = [];

        foreach ($headerLines as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $matches)) {
                $code = (int)$matches[1];
            } elseif (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))][] = trim($value);
            }
        }

        return new HttpResponse($code, $headers, $body);
    }
}

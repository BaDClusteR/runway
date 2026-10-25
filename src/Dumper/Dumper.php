<?php

declare(strict_types=1);

namespace Runway\Dumper;

use Runway\Request\IRequestRead;
use Runway\Singleton;
use JsonException;

/**
 * Debug output of variables: as is in the CLI, inside <pre> (HTML-escaped: the values can contain the user input) in
 * the browser.
 */
class Dumper extends Singleton implements IDumper {
    public function __construct(
        protected IRequestRead $request
    ) {}

    protected ?bool $isCLI = null;

    public function dump(mixed $var, mixed ...$vars): void {
        ob_start();
        var_dump($var, ...$vars);

        $this->output((string)ob_get_clean());
    }

    public function print(mixed $var, mixed ...$vars): void {
        $this->output(
            implode("\n", array_map(
                static fn(mixed $arg): string => print_r($arg, true),
                [$var, ...$vars]
            ))
        );
    }

    public function json(mixed $var, mixed ...$vars): void {
        $result = "";

        foreach ([$var, ...$vars] as $arg) {
            try {
                $json = json_encode($arg, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $json = "[Cannot encode variable to JSON]";
            }

            $result .= ($result ? "\n\n" : "") . $json;
        }

        $this->output($result);
    }

    public function export(mixed $var, mixed ...$vars): void {
        $result = "";

        foreach ([$var, ...$vars] as $arg) {
            $result .= ($result ? "\n\n" : "") . var_export($arg, true);
        }

        $this->output($result);
    }

    protected function output(string $text): void {
        echo $this->isCLI()
            ? $text
            : "<pre>" . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE) . "</pre>";
    }

    protected function isCLI(): bool {
        $this->isCLI ??= $this->request->isCLI();

        return $this->isCLI;
    }
}

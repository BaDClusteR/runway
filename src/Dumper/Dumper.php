<?php

namespace Runway\Dumper;

use Runway\Request\IRequest;
use Runway\Request\IRequestRead;
use Runway\Singleton;
use JsonException;
use Runway\Singleton\Container;

class Dumper extends Singleton implements IDumper {
    public function __construct(
        protected IRequestRead $request
    ) {}

    protected ?bool $isCLI = null;

    public function dump($var, ...$vars): void {
        if ($this->isCLI()) {
            echo "<pre>";
        }

        var_dump($var, ...$vars);

        if ($this->isCLI()) {
            echo "</pre>";
        }
    }

    public function print($var, ...$vars): void {
        if ($this->isCLI()) {
            echo "<pre>";
        }

        print_r($var, ...$vars);

        if ($this->isCLI()) {
            echo "</pre>";
        }
    }

    public function json($var, ...$vars): void {
        $result = "";

        foreach (func_get_args() as $arg) {
            try {
                $json = json_encode($arg, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $json = "[Cannot encode variable to JSON]";
            }

            $result .= ($result ? "\n\n" : "") . $json;
        }

        if ($this->isCLI()) {
            header("Content-type: application/json");
            die($result);
        }

        echo "<pre>$result</pre>";
    }

    public function export($var, ...$vars): void {
        $result = "";

        foreach (func_get_args() as $arg) {
            $result .= ($result ? "\n\n" : "") . var_export($arg, true);
        }

        echo $this->isCLI()
            ? $result
            : "<pre>$result</pre>";

    }

    protected function isCLI(): bool {
        $this->isCLI ??= $this->request->isCLI();

        return $this->isCLI;
    }
}
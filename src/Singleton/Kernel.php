<?php

declare(strict_types=1);

namespace Runway\Singleton;

use Runway\Exception\RuntimeException;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Event\IEventDispatcher;
use Runway\Request\IRequestRead;
use Runway\Request\IResponseRead;
use Runway\Singleton;

class Kernel extends Singleton implements IKernel {
    public function __construct(
        protected IRequestRead          $request,
        protected IEventDispatcher      $eventDispatcher,
        protected IEnvVariablesProvider $envVarsProvider
    ) {}

    public function processRequest(): void {
        $this->init();

        $this->processResponse(
            $this->request->process()
        );
    }

    public function processResponse(IResponseRead $response): void {
        http_response_code($response->getCode());

        foreach ($response->getHeaders() as $header => $value) {
            header("$header: $value");
        }

        foreach ($response->getCookies() as $cookie) {
            setcookie(
                $cookie->getName(),
                $cookie->getValue(),
                $cookie->getExpires(),
                $cookie->getPath(),
                $cookie->getDomain(),
                $cookie->isSecure(),
                $cookie->isHttpOnly()
            );
        }

        echo $response->getBody();
    }

    protected function init(): void {
        $this->eventDispatcher->dispatch(
            'kernel.init',
            null
        );

        $this->postProcessInit();
    }

    public function isDebugMode(): bool {
        return $this->envVarsProvider->getEnvVariable('APP_DEBUG') === true;
    }

    /** @noinspection PhpUndefinedConstantInspection */
    protected function postProcessInit(): void {
        if (!defined("PROJECT_ROOT")) {
            throw new RuntimeException("PROJECT_ROOT is not defined.");
        }

        define("MODULE_ROOT", PROJECT_ROOT . "/modules");
    }
}
<?php

declare(strict_types=1);

namespace Runway\Singleton;

use Runway\Exception\RuntimeException;
use Runway\Env\Provider\IEnvVariablesProvider;
use Runway\Event\IEventDispatcher;
use Runway\Request\IRequestRead;
use Runway\Request\IResponseRead;
use Runway\Request\IStreamedResponse;
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
            setcookie($cookie->getName(), $cookie->getValue(), [
                'expires'  => $cookie->getExpires(),
                'path'     => $cookie->getPath(),
                'domain'   => $cookie->getDomain(),
                'secure'   => $cookie->isSecure(),
                'httponly' => $cookie->isHttpOnly(),
                'samesite' => $cookie->getSameSite(),
            ]);
        }

        if (!$response instanceof IStreamedResponse) {
            echo $response->getBody();

            return;
        }

        // Every piece goes to the client at once: the output buffers would collect them (a buffer that cannot be
        // removed stays).
        while (ob_get_level() > 0 && ob_end_flush()) {
        }

        $response->sendBody();
    }

    protected function init(): void {
        $this->eventDispatcher->dispatch(
            'kernel.init',
            null
        );
    }

    public function isDebugMode(): bool {
        return $this->envVarsProvider->getEnvVariable('APP_DEBUG') === true;
    }
}
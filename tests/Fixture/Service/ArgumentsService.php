<?php

declare(strict_types=1);

namespace Runway\Tests\Fixture\Service;

use Runway\Event\IEventDispatcher;
use Runway\Logger\ILogger;

/**
 * The arguments of the config (a literal, a parameter, an environment variable, a service), an autowired service, a
 * nullable and a default value.
 */
final readonly class ArgumentsService {
    public function __construct(
        public string           $name,
        public string           $parameter,
        public mixed            $logLevel,
        public IGreeter         $greeter,
        public IEventDispatcher $autowired,
        public ?ILogger         $nullable,
        public int              $default = 5,
    ) {}
}

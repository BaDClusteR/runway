<?php

declare(strict_types=1);

namespace Runway\Event;

use Runway\Event\DTO\EventDTO;
use Runway\Event\Exception\EventException;
use Runway\Service\DTO\ConfigDTO;
use Runway\Service\DTO\ServiceDTO;
use Runway\Service\Provider\ConfigProvider;
use Runway\Singleton\Container;

class EventDispatcher implements IEventDispatcher {
    protected ConfigProvider $configProvider;

    protected ConfigDTO $config;

    /**
     * @var array<string, ServiceDTO>
     */
    protected array $services = [];

    /**
     * @var EventDTO $events
     */
    protected array $events = [];

    /**
     * @throws EventException
     */
    public function __construct() {
        $this->configProvider = new ConfigProvider();
        $this->config = $this->configProvider->getConfig();

        $this->defineServicesAndEvents();
    }

    protected function defineServicesAndEvents(): void {
        foreach ($this->config->getServiceConfig() as $service) {
            $this->services[$service->getName()] = $service;
            $serviceEvents = $service->getEvents();

            foreach ($serviceEvents as $event) {
                $this->events[$event->getName()][] = $event;
            }
        }
    }

    public function dispatch(string $eventName, mixed $envelope): void {
        if (isset($this->events[$eventName])) {
            foreach ($this->events[$eventName] as $event) {
                $service = Container::getInstance()->getService(
                    $event->getServiceName()
                );
                $method = $event->getMethod();

                if (method_exists($service, $method)) {
                    $service->$method($envelope);
                }

                unset($service);
            }
        }
    }
}
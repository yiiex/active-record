<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * PSR-14 dispatcher that records every dispatched event for assertions.
 */
class TestEventDispatcher implements EventDispatcherInterface
{
    /** @var object[] */
    private array $events = [];

    public function dispatch(object $event)
    {
        $this->events[] = $event;

        return $event;
    }

    /**
     * @return object[]
     */
    public function getEvents(?string $class = null): array
    {
        if ($class === null) {
            return $this->events;
        }

        return array_values(array_filter($this->events, static fn(object $event): bool => $event instanceof $class));
    }

    public function count(?string $class = null): int
    {
        return count($this->getEvents($class));
    }

    public function reset(): void
    {
        $this->events = [];
    }
}

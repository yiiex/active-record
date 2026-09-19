<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Behaviors;

use Yii1x\ActiveRecord\Model\Behavior;

/**
 * Behavior that counts validate events on Model instances.
 * For ActiveRecord use EventCounterBehavior which adds save/delete/find events.
 */
class ModelEventCounterBehavior extends Behavior
{
    private array $counters = [];

    public bool $cancelBeforeValidate = false;

    public function events(): array
    {
        return [
            'onBeforeValidate' => 'handleBeforeValidate',
            'onAfterValidate'  => 'handleAfterValidate',
        ];
    }

    public function handleBeforeValidate(object $event): void
    {
        $this->increment('beforeValidate');
        if ($this->cancelBeforeValidate && property_exists($event, 'isValid')) {
            $event->isValid = false;
        }
    }

    public function handleAfterValidate(object $event): void
    {
        $this->increment('afterValidate');
    }

    public function getCount(string $event): int
    {
        return $this->counters[$event] ?? 0;
    }

    public function getAllCounts(): array
    {
        return $this->counters;
    }

    public function reset(): void
    {
        $this->counters = [];
        $this->cancelBeforeValidate = false;
    }

    private function increment(string $event): void
    {
        $this->counters[$event] = ($this->counters[$event] ?? 0) + 1;
    }
}

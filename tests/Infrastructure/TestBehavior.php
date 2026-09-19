<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure;

use Yii1x\ActiveRecord\Model\Behavior;

/**
 * Test behavior for unit tests.
 */
class TestBehavior extends Behavior
{
    public bool $behaviorCalled = false;
    public int $eventHandled = 0;

    public function test(): int
    {
        $this->behaviorCalled = true;
        return 2;
    }

    public function events(): array
    {
        return [
            'onMyEvent' => 'handleMyEvent',
        ];
    }

    public function handleMyEvent($event): void
    {
        $this->eventHandled++;
    }
}

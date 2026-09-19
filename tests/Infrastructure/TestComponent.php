<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure;

use Yii1x\ActiveRecord\Model\CComponent;
use Yii1x\ActiveRecord\Model\Event;

/**
 * Test component for unit tests.
 */
class TestComponent extends CComponent
{
    private ?string $_text = 'default';
    public bool $eventHandled = false;

    public function getText(): ?string
    {
        return $this->_text;
    }

    public function setText(?string $value): void
    {
        $this->_text = $value;
    }

    public function onMyEvent(Event $event): void
    {
        $this->raiseEvent('onMyEvent', $event);
    }

    public function myEventHandler(Event $event): void
    {
        $this->eventHandled = true;
    }
}

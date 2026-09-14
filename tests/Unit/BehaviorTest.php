<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yii1x\ActiveRecord\Model\Event;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestBehavior;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestComponent;

/**
 * Unit tests for Behavior.
 * No database connection required.
 */
class BehaviorTest extends TestCase
{
    public function testAttachDetach(): void
    {
        $component = new TestComponent();
        $behavior = new TestBehavior();

        $behavior->attach($component);
        $this->assertTrue($behavior->getEnabled());
        $this->assertSame($component, $behavior->getOwner());

        $behavior->detach($component);
        $this->assertFalse($behavior->getEnabled());
        $this->assertNull($behavior->getOwner());
    }

    public function testEventsAutoAttach(): void
    {
        $component = new TestComponent();
        $behavior = new TestBehavior();

        $component->attachBehavior('test', $behavior);

        // Behavior should auto-attach its event handlers
        $this->assertTrue($component->hasEventHandler('onMyEvent'));

        // Trigger event
        $component->onMyEvent(new Event($component));
        $this->assertEquals(1, $behavior->eventHandled);
    }

    public function testEventsAutoDetach(): void
    {
        $component = new TestComponent();
        $behavior = new TestBehavior();

        $component->attachBehavior('test', $behavior);
        $component->detachBehavior('test');

        // Behavior should auto-detach its event handlers
        $this->assertFalse($component->hasEventHandler('onMyEvent'));
    }

    public function testSetEnabled(): void
    {
        $component = new TestComponent();
        $behavior = new TestBehavior();

        $component->attachBehavior('test', $behavior);

        $behavior->setEnabled(false);
        $this->assertFalse($behavior->getEnabled());
        $this->assertFalse($component->hasEventHandler('onMyEvent')); // handlers detached

        $behavior->setEnabled(true);
        $this->assertTrue($behavior->getEnabled());
        $this->assertTrue($component->hasEventHandler('onMyEvent')); // handlers re-attached
    }

    public function testMultipleEventHandlers(): void
    {
        $component = new TestComponent();
        $behavior1 = new TestBehavior();
        $behavior2 = new TestBehavior();

        $component->attachBehavior('test1', $behavior1);
        $component->attachBehavior('test2', $behavior2);

        // Trigger event
        $component->onMyEvent(new Event($component));

        // Both behaviors should handle the event
        $this->assertEquals(1, $behavior1->eventHandled);
        $this->assertEquals(1, $behavior2->eventHandled);
    }
}

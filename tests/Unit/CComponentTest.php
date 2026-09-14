<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yii1x\ActiveRecord\Model\Event;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestBehavior;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestComponent;

/**
 * Unit tests for CComponent.
 * No database connection required - pure component/behavior/event testing.
 */
class CComponentTest extends TestCase
{
    private TestComponent $component;

    protected function setUp(): void
    {
        $this->component = new TestComponent();
    }

    // ---------------------------------------------------------------
    //  Properties
    // ---------------------------------------------------------------

    public function testHasProperty(): void
    {
        $this->assertTrue($this->component->hasProperty('text'));
        $this->assertTrue($this->component->hasProperty('Text')); // case-insensitive
        $this->assertFalse($this->component->hasProperty('caption'));
    }

    public function testCanGetProperty(): void
    {
        $this->assertTrue($this->component->canGetProperty('text'));
        $this->assertTrue($this->component->canGetProperty('Text'));
        $this->assertFalse($this->component->canGetProperty('caption'));
    }

    public function testCanSetProperty(): void
    {
        $this->assertTrue($this->component->canSetProperty('text'));
        $this->assertFalse($this->component->canSetProperty('caption'));
    }

    public function testGetProperty(): void
    {
        $this->assertSame('default', $this->component->text);
        $this->assertSame('default', $this->component->Text);
    }

    public function testSetProperty(): void
    {
        $this->component->text = 'new value';
        $this->assertSame('new value', $this->component->text);
    }

    public function testGetPropertyNotDefined(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Property "Yii1x\ActiveRecord\Tests\Infrastructure\TestComponent::$caption" is not defined.');

        $this->component->caption;
    }

    public function testSetPropertyNotDefined(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Property "Yii1x\ActiveRecord\Tests\Infrastructure\TestComponent::$caption" is not defined.');

        $this->component->caption = 'value';
    }

    public function testIsset(): void
    {
        $this->assertTrue(isset($this->component->text));
    }

    public function testUnset(): void
    {
        $this->component->text = 'value';
        $this->assertTrue(isset($this->component->text));

        unset($this->component->text);
        $this->assertFalse(isset($this->component->text));
    }

    // ---------------------------------------------------------------
    //  Events
    // ---------------------------------------------------------------

    public function testHasEvent(): void
    {
        $this->assertTrue($this->component->hasEvent('onMyEvent'));
        $this->assertTrue($this->component->hasEvent('onmyevent')); // case-insensitive
        $this->assertFalse($this->component->hasEvent('onYourEvent'));
    }

    public function testHasEventHandler(): void
    {
        $this->assertFalse($this->component->hasEventHandler('onMyEvent'));

        $this->component->attachEventHandler('onMyEvent', [$this->component, 'myEventHandler']);
        $this->assertTrue($this->component->hasEventHandler('onMyEvent'));
    }

    public function testGetEventHandlers(): void
    {
        $handlers = $this->component->getEventHandlers('onMyEvent');
        $this->assertEquals(0, $handlers->getCount());

        $this->component->attachEventHandler('onMyEvent', [$this->component, 'myEventHandler']);
        $this->assertEquals(1, $handlers->getCount());
    }

    public function testGetEventHandlersNotDefined(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->component->getEventHandlers('onYourEvent');
    }

    public function testAttachEventHandler(): void
    {
        $this->component->attachEventHandler('onMyEvent', [$this->component, 'myEventHandler']);

        $handlers = $this->component->getEventHandlers('onMyEvent');
        $this->assertEquals(1, $handlers->getCount());
    }

    public function testDetachEventHandler(): void
    {
        $handler = [$this->component, 'myEventHandler'];
        $this->component->attachEventHandler('onMyEvent', $handler);

        $this->assertTrue($this->component->detachEventHandler('onMyEvent', $handler));
        $this->assertEquals(0, $this->component->getEventHandlers('onMyEvent')->getCount());

        $this->assertFalse($this->component->detachEventHandler('onMyEvent', $handler));
    }

    public function testRaiseEvent(): void
    {
        $this->component->attachEventHandler('onMyEvent', [$this->component, 'myEventHandler']);

        $this->assertFalse($this->component->eventHandled);
        $this->component->raiseEvent('onMyEvent', new Event($this->component));
        $this->assertTrue($this->component->eventHandled);
    }

    public function testRaiseEventViaMethod(): void
    {
        $this->component->attachEventHandler('onMyEvent', [$this->component, 'myEventHandler']);

        $this->assertFalse($this->component->eventHandled);
        $this->component->onMyEvent(new Event($this->component));
        $this->assertTrue($this->component->eventHandled);
    }

    // ---------------------------------------------------------------
    //  Behaviors
    // ---------------------------------------------------------------

    public function testAttachBehavior(): void
    {
        $behavior = new TestBehavior();
        $this->component->attachBehavior('testBehavior', $behavior);

        $this->assertSame($behavior, $this->component->asa('testBehavior'));
        $this->assertTrue($behavior->getEnabled());
    }

    public function testAttachBehaviorFromArray(): void
    {
        $this->component->attachBehavior('testBehavior', [
            'class' => TestBehavior::class,
        ]);

        $behavior = $this->component->asa('testBehavior');
        $this->assertInstanceOf(TestBehavior::class, $behavior);
    }

    public function testDetachBehavior(): void
    {
        $behavior = new TestBehavior();
        $this->component->attachBehavior('testBehavior', $behavior);

        $detached = $this->component->detachBehavior('testBehavior');
        $this->assertSame($behavior, $detached);
        $this->assertNull($this->component->asa('testBehavior'));
    }

    public function testDetachBehaviorNotExists(): void
    {
        $this->assertNull($this->component->detachBehavior('nonExistent'));
    }

    public function testDetachBehaviors(): void
    {
        $this->component->attachBehavior('test1', new TestBehavior());
        $this->component->attachBehavior('test2', new TestBehavior());

        $this->component->detachBehaviors();

        $this->assertNull($this->component->asa('test1'));
        $this->assertNull($this->component->asa('test2'));
    }

    public function testCallMethodFromBehavior(): void
    {
        $behavior = new TestBehavior();
        $this->component->attachBehavior('testBehavior', $behavior);

        $result = $this->component->test();
        $this->assertEquals(2, $result);
        $this->assertTrue($behavior->behaviorCalled);
    }

    public function testCallMethodNotExists(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->component->nonExistentMethod();
    }

    public function testAsa(): void
    {
        $behavior = new TestBehavior();
        $this->component->attachBehavior('testBehavior', $behavior);

        $this->assertSame($behavior, $this->component->asa('testBehavior'));
        $this->assertNull($this->component->asa('nonExistent'));
    }

    public function testEnableDisableBehavior(): void
    {
        $behavior = new TestBehavior();
        $this->component->attachBehavior('testBehavior', $behavior);

        $this->assertTrue($behavior->getEnabled());

        $this->component->disableBehavior('testBehavior');
        $this->assertFalse($behavior->getEnabled());

        $this->component->enableBehavior('testBehavior');
        $this->assertTrue($behavior->getEnabled());
    }

    public function testEnableDisableBehaviors(): void
    {
        $behavior1 = new TestBehavior();
        $behavior2 = new TestBehavior();
        $this->component->attachBehavior('test1', $behavior1);
        $this->component->attachBehavior('test2', $behavior2);

        $this->component->disableBehaviors();
        $this->assertFalse($behavior1->getEnabled());
        $this->assertFalse($behavior2->getEnabled());

        $this->component->enableBehaviors();
        $this->assertTrue($behavior1->getEnabled());
        $this->assertTrue($behavior2->getEnabled());
    }

    public function testDisabledBehaviorMethodNotCalled(): void
    {
        $behavior = new TestBehavior();
        $this->component->attachBehavior('testBehavior', $behavior);
        $this->component->disableBehavior('testBehavior');

        $this->expectException(\RuntimeException::class);
        $this->component->test(); // should throw because behavior is disabled
    }
}

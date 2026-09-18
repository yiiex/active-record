<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\ModelWithEvents;

class ModelEventsTest extends TestCase
{
    // ===== beforeValidate / afterValidate =====

    public function testBeforeValidateCalled(): void
    {
        $model = new ModelWithEvents();
        $model->name = 'test';
        $model->email = 'test@example.com';

        $this->assertTrue($model->validate());
        $this->assertSame(1, $model->counter()->getCount('beforeValidate'));
        $this->assertSame(1, $model->counter()->getCount('afterValidate'));
    }

    public function testBeforeValidateCanCancelValidation(): void
    {
        $model = new ModelWithEvents();
        $model->counter()->cancelBeforeValidate = true;
        $model->name = 'test';
        $model->email = 'test@example.com';

        $this->assertFalse($model->validate());
        $this->assertSame(1, $model->counter()->getCount('beforeValidate'));
        $this->assertSame(0, $model->counter()->getCount('afterValidate'));
    }

    // ===== Custom events =====

    public function testCustomEventWithClosure(): void
    {
        $called = false;

        $model = new ModelWithEvents();
        $model->onCustomEvent = function () use (&$called): void {
            $called = true;
        };

        $model->customEvent();

        $this->assertTrue($called);
    }

    public function testCustomEventWithCallableArray(): void
    {
        $handler = new class {
            public bool $called = false;

            public function handle(object $event): void
            {
                $this->called = true;
            }
        };

        $model = new ModelWithEvents();
        $model->onCustomEvent = [$handler, 'handle'];

        $model->customEvent();

        $this->assertTrue($handler->called);
    }

    public function testEventSenderIsModel(): void
    {
        $sender = null;

        $model = new ModelWithEvents();
        $model->onCustomEvent = function ($event) use (&$sender): void {
            $sender = $event->sender;
        };

        $model->customEvent();

        $this->assertSame($model, $sender);
    }

    public function testMultipleHandlersCalledInOrder(): void
    {
        $order = [];

        $model = new ModelWithEvents();
        $model->attachEventHandler('onCustomEvent', function () use (&$order): void {
            $order[] = 'first';
        });
        $model->attachEventHandler('onCustomEvent', function () use (&$order): void {
            $order[] = 'second';
        });
        $model->attachEventHandler('onCustomEvent', function () use (&$order): void {
            $order[] = 'third';
        });

        $model->customEvent();

        $this->assertSame(['first', 'second', 'third'], $order);
    }

    public function testDetachEventHandler(): void
    {
        $count = 0;
        $handler = function () use (&$count): void {
            $count++;
        };

        $model = new ModelWithEvents();
        $model->attachEventHandler('onCustomEvent', $handler);
        $model->customEvent();
        $this->assertSame(1, $count);

        $model->detachEventHandler('onCustomEvent', $handler);
        $model->customEvent();
        $this->assertSame(1, $count);
    }

    public function testHasEventHandler(): void
    {
        $model = new ModelWithEvents();
        $this->assertFalse($model->hasEventHandler('onCustomEvent'));

        $model->onCustomEvent = fn () => null;
        $this->assertTrue($model->hasEventHandler('onCustomEvent'));
    }

    public function testEventHandledStopsPropagation(): void
    {
        $order = [];

        $model = new ModelWithEvents();
        $model->attachEventHandler('onCustomEvent', function ($event) use (&$order): void {
            $order[] = 'first';
            $event->handled = true;
        });
        $model->attachEventHandler('onCustomEvent', function () use (&$order): void {
            $order[] = 'second';
        });

        $model->customEvent();

        $this->assertSame(['first'], $order);
    }
}

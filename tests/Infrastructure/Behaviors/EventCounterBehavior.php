<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Behaviors;

use Yii1x\ActiveRecord\ActiveRecordBehavior;

class EventCounterBehavior extends ActiveRecordBehavior
{
    private array $counters = [];

    public bool $cancelBeforeValidate = false;
    public bool $cancelBeforeSave = false;
    public bool $cancelBeforeDelete = false;
    public array $attributeModifiers = [];

    public function events(): array
    {
        return [
            'onBeforeValidate' => 'handleBeforeValidate',
            'onAfterValidate' => 'handleAfterValidate',
            'onBeforeSave' => 'handleBeforeSave',
            'onAfterSave' => 'handleAfterSave',
            'onBeforeDelete' => 'handleBeforeDelete',
            'onAfterDelete' => 'handleAfterDelete',
            'onBeforeFind' => 'handleBeforeFind',
            'onAfterFind' => 'handleAfterFind',
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

    public function handleBeforeSave(object $event): void
    {
        $this->increment('beforeSave');
        $this->increment($this->getOwner()->isNewRecord ? 'beforeSaveInsert' : 'beforeSaveUpdate');

        foreach ($this->attributeModifiers as $attribute => $value) {
            $this->getOwner()->$attribute = $value;
        }

        if ($this->cancelBeforeSave && property_exists($event, 'isValid')) {
            $event->isValid = false;
        }
    }

    public function handleAfterSave(object $event): void
    {
        $this->increment('afterSave');
    }

    public function handleBeforeDelete(object $event): void
    {
        $this->increment('beforeDelete');
        if ($this->cancelBeforeDelete && property_exists($event, 'isValid')) {
            $event->isValid = false;
        }
    }

    public function handleAfterDelete(object $event): void
    {
        $this->increment('afterDelete');
    }

    public function handleBeforeFind(object $event): void
    {
        $this->increment('beforeFind');
    }

    public function handleAfterFind(object $event): void
    {
        $this->increment('afterFind');
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
        $this->cancelBeforeSave = false;
        $this->cancelBeforeDelete = false;
        $this->attributeModifiers = [];
    }

    private function increment(string $event): void
    {
        $this->counters[$event] = ($this->counters[$event] ?? 0) + 1;
    }
}

<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\Model\Event;
use Yii1x\ActiveRecord\Model\Model;
use Yii1x\ActiveRecord\Tests\Infrastructure\Behaviors\ModelEventCounterBehavior;

class ModelWithEvents extends Model
{
    public ?int $id = null;
    public ?string $name = null;
    public ?string $email = null;

    public function __construct(string $scenario = 'insert')
    {
        $this->scenario = $scenario;
        $this->attachBehaviors($this->behaviors());
    }

    public function attributeNames(): array
    {
        return ['id', 'name', 'email'];
    }

    public function rules(): array
    {
        return [
            [['name', 'email'], 'required'],
            [['name', 'email'], 'length', 'max' => 255],
            ['email', 'email'],
        ];
    }

    public function behaviors(): array
    {
        return [
            'counter' => [
                'class' => ModelEventCounterBehavior::class,
            ],
        ];
    }

    public function counter(): ModelEventCounterBehavior
    {
        return $this->asa('counter');
    }

    public function onCustomEvent(Event $event): void
    {
        $this->raiseEvent('onCustomEvent', $event);
    }

    public function customEvent(): void
    {
        $this->onCustomEvent(new Event($this));
    }
}

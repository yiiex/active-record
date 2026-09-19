<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Model\Event;
use Yii1x\ActiveRecord\Tests\Infrastructure\Behaviors\EventCounterBehavior;

/**
 * User model with EventCounterBehavior auto-attached.
 */
class UserWithEvents extends ActiveRecord
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'counter' => EventCounterBehavior::class,
        ]);
    }

    public function tableName(): string
    {
        return 'users';
    }

    public function rules(): array
    {
        return [
            [['username', 'email', 'password'], 'required'],
            [['username', 'email'], 'length', 'max' => 255],
            ['email', 'email'],
        ];
    }

    public function relations(): array
    {
        return [
            'posts' => [self::HAS_MANY, PostWithEvents::class, 'author_id'],
            'profile' => [self::HAS_ONE, Profile::class, 'user_id'],
            'postCount' => [self::STAT, PostWithEvents::class, 'author_id'],
        ];
    }

    /**
     * Convenience accessor for the counter behavior.
     */
    public function counter(): EventCounterBehavior
    {
        return $this->asa('counter');
    }
}

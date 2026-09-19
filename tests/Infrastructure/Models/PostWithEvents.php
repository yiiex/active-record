<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Tests\Infrastructure\Behaviors\EventCounterBehavior;

/**
 * Post model with EventCounterBehavior auto-attached.
 */
class PostWithEvents extends ActiveRecord
{
    public function behaviors(): array
    {
        return array_merge(parent::behaviors(), [
            'counter' => EventCounterBehavior::class,
        ]);
    }

    public function tableName(): string
    {
        return 'posts';
    }

    public function rules(): array
    {
        return [
            [['title', 'author_id'], 'required'],
            ['title', 'length', 'max' => 255],
            ['content', 'safe'],
            ['author_id', 'numerical'],
        ];
    }

    public function relations(): array
    {
        return [
            'author' => [self::BELONGS_TO, UserWithEvents::class, 'author_id'],
            'comments' => [self::HAS_MANY, Comment::class, 'post_id'],
            'commentCount' => [self::STAT, Comment::class, 'post_id'],
        ];
    }

    public function counter(): EventCounterBehavior
    {
        return $this->asa('counter');
    }
}

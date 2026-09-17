<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;

class Comment extends ActiveRecord
{
    public function tableName(): string
    {
        return 'comments';
    }

    public function rules(): array
    {
        return [
            [['content', 'post_id', 'user_id'], 'required'],
            ['content', 'safe'],
            [['post_id', 'user_id'], 'numerical'],
        ];
    }

    public function relations(): array
    {
        return [
            'post' => [self::BELONGS_TO, Post::class, 'post_id'],
            'author' => [self::BELONGS_TO, User::class, 'user_id'],
        ];
    }
}

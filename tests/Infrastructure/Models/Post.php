<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;

class Post extends ActiveRecord
{
    public function tableName(): string
    {
        return 'posts';
    }

    public function rules(): array
    {
        return [
            [['title', 'user_id'], 'required'],
            ['title', 'length', 'max' => 255],
            ['content', 'safe'],
            ['user_id', 'numerical'],
        ];
    }

    public function relations(): array
    {
        return [
            'author' => [self::BELONGS_TO, User::class, 'user_id'],
            'comments' => [self::HAS_MANY, Comment::class, 'post_id'],
        ];
    }
}

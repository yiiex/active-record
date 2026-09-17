<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;

class User extends ActiveRecord
{
    public function tableName(): string
    {
        return 'users';
    }

    public function rules(): array
    {
        return [
            [['username', 'email'], 'required'],
            [['username', 'email'], 'length', 'max' => 255],
            ['email', 'email'],
            [['username'], 'unique'],
            [['email'], 'unique'],
        ];
    }

    public function relations(): array
    {
        return [
            'posts' => [self::HAS_MANY, Post::class, 'author_id'],
            'comments' => [self::HAS_MANY, Comment::class, 'author_id'],
            'profile' => [self::HAS_ONE, Profile::class, 'user_id'],
            'commentCount' => [self::STAT, Comment::class, 'author_id'],
            'postCount' => [self::STAT, Post::class, 'author_id'],
        ];
    }
}

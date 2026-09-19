<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

/**
 * User model declaring relations with named scopes.
 */
class UserWithScopedPosts extends User
{
    public function relations(): array
    {
        return array_merge(parent::relations(), [
            'allPosts' => [self::HAS_MANY, PostWithScopes::class, 'author_id'],
            'posts' => [self::HAS_MANY, PostWithScopes::class, 'author_id', 'scopes' => 'post23'],
            'recentPosts' => [self::HAS_MANY, PostWithScopes::class, 'author_id', 'scopes' => 'recent'],
            'postCount' => [self::STAT, PostWithScopes::class, 'author_id', 'scopes' => 'post23'],
        ]);
    }
}

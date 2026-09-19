<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

/**
 * Category model declaring a MANY_MANY STAT relation with a named scope.
 */
class CategoryWithScopedPosts extends Category
{
    public function relations(): array
    {
        return array_merge(parent::relations(), [
            'postCount' => [self::STAT, PostWithScopes::class, 'post_category(category_id, post_id)', 'scopes' => 'post23'],
        ]);
    }
}

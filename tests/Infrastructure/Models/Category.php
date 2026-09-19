<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;

class Category extends ActiveRecord
{
    public function tableName(): string
    {
        return 'categories';
    }

    public function rules(): array
    {
        return [
            ['name', 'required'],
            ['name', 'length', 'max' => 255],
            ['parent_id', 'numerical'],
        ];
    }

    public function relations(): array
    {
        return [
            'parent' => [self::BELONGS_TO, Category::class, 'parent_id'],
            'children' => [self::HAS_MANY, Category::class, 'parent_id'],
            'posts' => [self::MANY_MANY, Post::class, 'post_category(category_id, post_id)'],
        ];
    }
}

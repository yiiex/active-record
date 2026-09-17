<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;

class Order extends ActiveRecord
{
    public function tableName(): string
    {
        return 'orders';
    }

    public function rules(): array
    {
        return [
            [['key1', 'key2'], 'required'],
            ['name', 'length', 'max' => 255],
            [['key1', 'key2'], 'numerical'],
        ];
    }

    public function relations(): array
    {
        return [
            'items' => [self::HAS_MANY, Item::class, ['col1' => 'key1', 'col2' => 'key2']],
        ];
    }
}

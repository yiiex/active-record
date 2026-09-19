<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;

class Item extends ActiveRecord
{
    public function tableName(): string
    {
        return 'items';
    }

    public function rules(): array
    {
        return [
            [['name', 'col1', 'col2'], 'required'],
            ['name', 'length', 'max' => 255],
            [['col1', 'col2'], 'numerical'],
        ];
    }

    public function relations(): array
    {
        return [
            'order' => [self::BELONGS_TO, Order::class, ['col1' => 'key1', 'col2' => 'key2']],
        ];
    }
}

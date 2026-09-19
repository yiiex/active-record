<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;

class Profile extends ActiveRecord
{
    public function tableName(): string
    {
        return 'profiles';
    }

    public function rules(): array
    {
        return [
            [['first_name', 'last_name', 'user_id'], 'required'],
            [['first_name', 'last_name'], 'length', 'max' => 255],
            ['user_id', 'numerical'],
        ];
    }

    public function relations(): array
    {
        return [
            'user' => [self::BELONGS_TO, User::class, 'user_id'],
        ];
    }
}

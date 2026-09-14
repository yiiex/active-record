<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\Model\Model;

/**
 * Test model for unit tests.
 */
class TestModel extends Model
{
    public ?int $attr1 = null;
    public ?int $attr2 = null;
    public ?string $attr3 = null;
    public ?string $password = null;
    public ?string $password2 = null;

    public function attributeNames(): array
    {
        return ['attr1', 'attr2', 'attr3', 'password', 'password2'];
    }

    public function rules(): array
    {
        return [
            ['attr1', 'required'],
            ['attr1', 'numerical', 'min' => 1, 'max' => 5],
            ['attr2', 'numerical', 'min' => 1, 'max' => 5],
            ['attr3', 'unsafe'],
            ['password', 'required', 'on' => ['register']],
            ['password2', 'compare', 'compareAttribute' => 'password', 'on' => ['register']],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'attr1' => 'First Attribute',
            'attr2' => 'Second Attribute',
        ];
    }
}

<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;

/**
 * ActiveRecord on the users table exposing unique rules per scenario.
 */
class UniqueValidationModel extends ActiveRecord
{
    public function tableName(): string
    {
        return 'users';
    }

    public function rules(): array
    {
        return [
            [['username'], 'unique', 'on' => 'simple'],
            [['username'], 'unique', 'caseSensitive' => false, 'on' => 'case_insensitive'],
            [['username'], 'unique', 'caseSensitive' => true, 'on' => 'case_sensitive'],
            [['username'], 'unique', 'message' => '{attribute} "{value}" already used.', 'on' => 'custom_message'],
            [['username'], 'unique', 'attributeName' => 'email', 'on' => 'attribute_name'],
            [['username'], 'unique', 'className' => User::class, 'on' => 'class_name'],
            [['username'], 'unique', 'criteria' => ['condition' => 'id > 100'], 'on' => 'criteria'],
            [['username'], 'unique', 'allowEmpty' => true, 'on' => 'allow_empty'],
            [['username'], 'unique', 'attributeName' => 'does_not_exist', 'allowEmpty' => false, 'on' => 'missing_column'],
        ];
    }
}

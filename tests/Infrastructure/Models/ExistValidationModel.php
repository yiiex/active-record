<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;

/**
 * ActiveRecord on the users table exposing exist rules per scenario.
 */
class ExistValidationModel extends ActiveRecord
{
    public function tableName(): string
    {
        return 'users';
    }

    public function rules(): array
    {
        return [
            [['username'], 'exist', 'on' => 'simple'],
            [['username'], 'exist', 'caseSensitive' => false, 'on' => 'case_insensitive'],
            [['username'], 'exist', 'caseSensitive' => true, 'on' => 'case_sensitive'],
            [['username'], 'exist', 'message' => '{attribute} "{value}" must exist.', 'on' => 'custom_message'],
            [['username'], 'exist', 'attributeName' => 'email', 'on' => 'attribute_name'],
            [['username'], 'exist', 'className' => User::class, 'on' => 'class_name'],
            [['username'], 'exist', 'criteria' => ['condition' => 'id > 100'], 'on' => 'criteria'],
            [['username'], 'exist', 'allowEmpty' => true, 'on' => 'allow_empty'],
            [['username'], 'exist', 'attributeName' => 'does_not_exist', 'allowEmpty' => false, 'on' => 'missing_column'],
        ];
    }
}

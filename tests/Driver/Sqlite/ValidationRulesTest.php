<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Sqlite;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractValidationRulesTest;

class ValidationRulesTest extends AbstractValidationRulesTest
{
    protected function driverName(): string
    {
        return 'sqlite';
    }
}

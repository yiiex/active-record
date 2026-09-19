<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Sqlite;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractActiveRecordCrudTest;

class ActiveRecordCrudTest extends AbstractActiveRecordCrudTest
{
    protected function driverName(): string
    {
        return 'sqlite';
    }
}

<?php

namespace Yii1x\ActiveRecord\Tests\Driver\Sqlite;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbConnectionTest;

class DbConnectionTest extends AbstractDbConnectionTest
{

    protected function driverName(): string
    {
        return 'sqlite';
    }
}

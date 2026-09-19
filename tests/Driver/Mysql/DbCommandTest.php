<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Mysql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbCommandTest;

class DbCommandTest extends AbstractDbCommandTest
{
    protected function driverName(): string
    {
        return 'mysql';
    }
}

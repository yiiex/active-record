<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Sqlite;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbTransactionTest;

class DbTransactionTest extends AbstractDbTransactionTest
{
    protected function driverName(): string
    {
        return 'sqlite';
    }
}

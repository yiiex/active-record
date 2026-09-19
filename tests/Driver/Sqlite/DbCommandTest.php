<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Sqlite;

use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbCommandTest;

class DbCommandTest extends AbstractDbCommandTest
{
    protected function driverName(): string
    {
        return 'sqlite';
    }

    public function testPrepareInvalidSql(): void
    {
        $this->expectException(DbException::class);

        $this->connection->createCommand('Bad SQL')->prepare();
    }
}

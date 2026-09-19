<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Mysql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbDataReaderTest;

class DbDataReaderTest extends AbstractDbDataReaderTest
{
    protected function driverName(): string
    {
        return 'mysql';
    }

    /**
     * MySQL and PostgreSQL support rowCount() for SELECT statements.
     * SQLite does not (returns 0 or -1), so this test is driver-specific.
     */
    public function testRowCount(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();
        $this->assertEquals(5, $reader->getRowCount());
    }
}

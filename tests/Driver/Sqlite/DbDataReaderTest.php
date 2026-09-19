<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Sqlite;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbDataReaderTest;

class DbDataReaderTest extends AbstractDbDataReaderTest
{
    protected function driverName(): string
    {
        return 'sqlite';
    }

    /**
     * SQLite does not support rowCount() for SELECT statements.
     * Returns 0 or -1 depending on PHP version.
     * See: https://www.php.net/manual/en/pdostatement.rowcount.php
     */
    public function testRowCountNotSupported(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();

        // SQLite returns 0 or -1 for SELECT, not the actual row count
        $this->assertLessThanOrEqual(0, $reader->getRowCount());
    }
}

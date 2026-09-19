<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Pgsql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbConnectionTest;

class DbConnectionTest extends AbstractDbConnectionTest
{
    protected function driverName(): string
    {
        return 'pgsql';
    }

    public function testLastInsertID(): void
    {
        $table = $this->connection->getSchema()->getTable('posts');
        $maxBefore = (int)$this->connection->createCommand('SELECT MAX(id) FROM posts')->queryScalar();

        $sql = "INSERT INTO posts(title,create_time,author_id) VALUES('test post','2000-01-01',1)";
        $this->connection->createCommand($sql)->execute();

        // PostgreSQL requires the sequence name
        $this->assertEquals($maxBefore + 1, (int)$this->connection->getLastInsertID($table->sequenceName));
    }
}

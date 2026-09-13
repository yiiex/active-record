<?php

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
        $sql = "INSERT INTO posts(title,create_time,author_id) VALUES('test post','2000-01-01',1)";
        $this->connection->createCommand($sql)->execute();

        // PostgreSQL requires sequence name
        $this->assertEquals(6, $this->connection->getLastInsertID('posts_id_seq'));
    }
}

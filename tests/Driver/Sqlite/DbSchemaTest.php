<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Sqlite;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbSchemaTest;

class DbSchemaTest extends AbstractDbSchemaTest
{
    protected function driverName(): string
    {
        return 'sqlite';
    }

    public function testQuoteTableName(): void
    {
        $quoted = $this->schema->quoteTableName('posts');
        $this->assertEquals('\'posts\'', $quoted);
    }

    public function testQuoteColumnName(): void
    {
        $quoted = $this->schema->quoteColumnName('id');
        $this->assertEquals('"id"', $quoted);
    }

    public function testTableRawName(): void
    {
        $table = $this->schema->getTable('posts');
        $this->assertEquals('\'posts\'', $table->rawName);
    }

    public function testColumnRawName(): void
    {
        $table = $this->schema->getTable('posts');
        $idColumn = $table->getColumn('id');
        $this->assertEquals('"id"', $idColumn->rawName);
    }

    public function testCheckIntegrityDisabled(): void
    {
        $this->schema->checkIntegrity(false);

        try {
            // This should succeed with integrity check disabled
            $this->connection->createCommand("INSERT INTO profiles (first_name, last_name, user_id) VALUES ('orphan', 'profile', 9999)")->execute();

            $count = (int)$this->connection->createCommand('SELECT COUNT(*) FROM profiles WHERE user_id=9999')->queryScalar();
            $this->assertEquals(1, $count);
        } finally {
            $this->schema->checkIntegrity(true);
        }
    }

    public function testCheckIntegrityEnabled(): void
    {
        $this->schema->checkIntegrity(false);
        $this->connection->createCommand(
            "INSERT INTO profiles (first_name, last_name, user_id) VALUES ('orphan1', 'profile', 9999)"
        )->execute();

        $count = (int)$this->connection->createCommand('SELECT COUNT(*) FROM profiles WHERE user_id=9999')->queryScalar();
        $this->assertEquals(1, $count);

        $this->schema->checkIntegrity(true);

        $this->expectException(\Exception::class);
        $this->connection->createCommand(
            "INSERT INTO profiles (first_name, last_name, user_id) VALUES ('orphan2', 'profile', 8888)"
        )->execute();
    }
}

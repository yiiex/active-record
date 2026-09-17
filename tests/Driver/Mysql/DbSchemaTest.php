<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Mysql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbSchemaTest;

class DbSchemaTest extends AbstractDbSchemaTest
{
    protected function driverName(): string
    {
        return 'mysql';
    }

    public function testQuoteTableName(): void
    {
        $quoted = $this->schema->quoteTableName('posts');
        $this->assertEquals('`posts`', $quoted);
    }

    public function testQuoteColumnName(): void
    {
        $quoted = $this->schema->quoteColumnName('id');
        $this->assertEquals('`id`', $quoted);
    }

    public function testTableRawName(): void
    {
        $table = $this->schema->getTable('posts');
        $this->assertEquals('`posts`', $table->rawName);
    }

    public function testColumnRawName(): void
    {
        $table = $this->schema->getTable('posts');
        $idColumn = $table->getColumn('id');
        $this->assertEquals('`id`', $idColumn->rawName);
    }

    public function testResetSequence(): void
    {
        $table = $this->schema->getTable('users');

        $this->connection->createCommand('DELETE FROM users')->execute();

        $this->schema->resetSequence($table);

        $this->connection->createCommand("INSERT INTO users (username, password, email) VALUES ('test', 'pass', 'email')")->execute();
        $newId = (int)$this->connection->createCommand('SELECT MAX(id) FROM users')->queryScalar();

        $this->assertEquals(1, $newId);
    }

    public function testResetSequenceWithValue(): void
    {
        $table = $this->schema->getTable('users');

        $this->connection->createCommand('DELETE FROM users')->execute();

        $this->schema->resetSequence($table, 100);

        $this->connection->createCommand("INSERT INTO users (username, password, email) VALUES ('test', 'pass', 'email')")->execute();
        $newId = (int)$this->connection->createCommand('SELECT MAX(id) FROM users')->queryScalar();

        $this->assertEquals(100, $newId);
    }

    public function testCheckIntegrity(): void
    {
        $this->schema->checkIntegrity(false);
        $this->schema->checkIntegrity(true);
        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Pgsql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbSchemaTest;

class DbSchemaTest extends AbstractDbSchemaTest
{
    protected function driverName(): string
    {
        return 'pgsql';
    }

    public function testQuoteTableName(): void
    {
        $quoted = $this->schema->quoteTableName('posts');
        $this->assertEquals('"posts"', $quoted);
    }

    public function testQuoteColumnName(): void
    {
        $quoted = $this->schema->quoteColumnName('id');
        $this->assertEquals('"id"', $quoted);
    }

    public function testTableRawName(): void
    {
        $table = $this->schema->getTable('posts');
        $this->assertEquals('"posts"', $table->rawName);
    }

    public function testColumnRawName(): void
    {
        $table = $this->schema->getTable('posts');
        $idColumn = $table->getColumn('id');
        $this->assertEquals('"id"', $idColumn->rawName);
    }

    public function testTableSequenceName(): void
    {
        $table = $this->schema->getTable('posts');
        $this->assertNotNull($table->sequenceName);
        $this->assertStringContainsString('posts', $table->sequenceName);
    }
}

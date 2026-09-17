<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Pgsql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDDLTest;

class DDLTest extends AbstractDDLTest
{
    protected function driverName(): string
    {
        return 'pgsql';
    }

    public function testDropForeignKey(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_fk_parent2', [
            'id' => 'pk',
        ]))->execute();

        $this->connection->createCommand($this->schema->createTable('test_fk_child2', [
            'id' => 'pk',
            'parent_id' => 'integer',
        ]))->execute();

        $this->connection->createCommand($this->schema->addForeignKey('fk_to_drop', 'test_fk_child2', 'parent_id', 'test_fk_parent2', 'id'))->execute();

        $sql = $this->schema->dropForeignKey('fk_to_drop', 'test_fk_child2');
        // PostgreSQL uses DROP CONSTRAINT
        $this->assertStringContainsString('DROP CONSTRAINT', $sql);
        $this->assertStringContainsString('fk_to_drop', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->assertTrue(true);
    }

    public function testDropPrimaryKey(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_drop_pk', [
            'id' => 'integer NOT NULL',
            'name' => 'string',
            'primary key (id)',
        ]))->execute();

        $constraintName = 'test_drop_pk_pkey';

        $sql = $this->schema->dropPrimaryKey($constraintName, 'test_drop_pk');
        $this->assertStringContainsString('DROP CONSTRAINT', $sql);
        $this->assertStringContainsString($constraintName, $sql);

        $this->connection->createCommand($sql)->execute();
        $this->schema->refresh();

        $table = $this->schema->getTable('test_drop_pk');
        $this->assertNull($table->primaryKey);
    }

    public function testRenameColumn(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_rename_col', [
            'id' => 'pk',
            'old_name' => 'string',
        ]))->execute();

        $sql = $this->schema->renameColumn('test_rename_col', 'old_name', 'new_name');
        $this->assertStringContainsString('RENAME COLUMN', $sql);
        $this->assertStringContainsString('old_name', $sql);
        $this->assertStringContainsString('new_name', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->schema->refresh();

        $table = $this->schema->getTable('test_rename_col');
        $this->assertArrayNotHasKey('old_name', $table->columns);
        $this->assertArrayHasKey('new_name', $table->columns);
    }
}

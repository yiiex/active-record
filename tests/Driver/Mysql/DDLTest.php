<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Mysql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDDLTest;

class DDLTest extends AbstractDDLTest
{
    protected function driverName(): string
    {
        return 'mysql';
    }

    public function testCreateTableWithEngine(): void
    {
        $sql = $this->schema->createTable('test_engine', [
            'id' => 'pk',
        ], 'ENGINE=InnoDB');

        $this->assertStringContainsString('ENGINE=InnoDB', $sql);
    }

    public function testRenameColumn(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_rename_col', [
            'id' => 'pk',
            'old_name' => 'string',
        ]))->execute();

        $sql = $this->schema->renameColumn('test_rename_col', 'old_name', 'new_name');
        $this->assertStringContainsString('CHANGE', $sql);
        $this->assertStringContainsString('old_name', $sql);
        $this->assertStringContainsString('new_name', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->schema->refresh();

        $table = $this->schema->getTable('test_rename_col');
        $this->assertArrayNotHasKey('old_name', $table->columns);
        $this->assertArrayHasKey('new_name', $table->columns);
    }

    public function testDropPrimaryKey(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_drop_pk', [
            'id' => 'integer NOT NULL',
            'name' => 'string',
            'primary key (id)',
        ]))->execute();

        $sql = $this->schema->dropPrimaryKey('pk_test', 'test_drop_pk');
        $this->assertStringContainsString('DROP PRIMARY KEY', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->schema->refresh();

        $table = $this->schema->getTable('test_drop_pk');
        $this->assertNull($table->primaryKey);
    }
}

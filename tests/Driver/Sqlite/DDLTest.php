<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Sqlite;

use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDDLTest;

class DDLTest extends AbstractDDLTest
{
    protected function driverName(): string
    {
        return 'sqlite';
    }

    public function testDropColumn(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Dropping DB column is not supported by SQLite');

        $this->connection->createCommand($this->schema->createTable('test_drop_col', [
            'id' => 'pk',
            'to_drop' => 'string',
        ]))->execute();

        $this->schema->dropColumn('test_drop_col', 'to_drop');
    }

    public function testRenameColumn(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Renaming a DB column is not supported by SQLite');

        $this->connection->createCommand($this->schema->createTable('test_rename_col', [
            'id' => 'pk',
            'old_name' => 'string',
        ]))->execute();

        $this->schema->renameColumn('test_rename_col', 'old_name', 'new_name');
    }

    public function testAlterColumn(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Altering a DB column is not supported by SQLite');

        $this->connection->createCommand($this->schema->createTable('test_alter_col', [
            'id' => 'pk',
            'name' => 'string',
        ]))->execute();

        $this->schema->alterColumn('test_alter_col', 'name', 'text');
    }

    public function testAddForeignKey(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Adding a foreign key constraint to an existing table is not supported by SQLite');

        $this->connection->createCommand($this->schema->createTable('test_fk_parent', [
            'id' => 'pk',
        ]))->execute();

        $this->connection->createCommand($this->schema->createTable('test_fk_child', [
            'id' => 'pk',
            'parent_id' => 'integer',
        ]))->execute();

        $this->schema->addForeignKey('fk_test', 'test_fk_child', 'parent_id', 'test_fk_parent', 'id');
    }

    public function testDropForeignKey(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Dropping a foreign key constraint is not supported by SQLite');

        $this->connection->createCommand($this->schema->createTable('test_fk_parent2', [
            'id' => 'pk',
        ]))->execute();

        $this->connection->createCommand($this->schema->createTable('test_fk_child2', [
            'id' => 'pk',
            'parent_id' => 'integer',
        ]))->execute();

        $this->schema->dropForeignKey('fk_to_drop', 'test_fk_child2');
    }

    public function testAddPrimaryKey(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Adding a primary key after table has been created is not supported by SQLite');

        $this->connection->createCommand($this->schema->createTable('test_add_pk', [
            'id' => 'integer',
            'name' => 'string',
        ]))->execute();

        $this->schema->addPrimaryKey('pk_test', 'test_add_pk', 'id');
    }

    public function testDropPrimaryKey(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Removing a primary key after table has been created is not supported by SQLite');

        $this->connection->createCommand($this->schema->createTable('test_drop_pk', [
            'id' => 'integer NOT NULL',
            'name' => 'string',
            'primary key (id)',
        ]))->execute();

        $this->schema->dropPrimaryKey('pk_test', 'test_drop_pk');
    }
}

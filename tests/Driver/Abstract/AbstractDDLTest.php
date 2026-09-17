<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\Db\Schema\DbSchema;

abstract class AbstractDDLTest extends AbstractDatabaseTest
{
    protected DbSchema $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = $this->connection->getSchema();
        $this->cleanupTestTables();
    }

    protected function cleanupTestTables(): void
    {
        $testTables = [
            'test_fk_child',
            'test_fk_parent',
            'test_fk_child2',
            'test_fk_parent2',
            'test_table',
            'test_table2',
            'test_drop',
            'test_rename_new',
            'test_rename_old',
            'test_add_col',
            'test_drop_col',
            'test_rename_col',
            'test_alter_col',
            'test_index',
            'test_unique_idx',
            'test_drop_idx',
            'test_add_pk',
            'test_drop_pk',
            'test_engine',
        ];

        foreach ($testTables as $table) {
            $this->connection->createCommand("DROP TABLE IF EXISTS {$table}")->execute();
        }
    }

    // ---------------------------------------------------------------
    //  CREATE TABLE
    // ---------------------------------------------------------------

    public function testCreateTable(): void
    {
        $sql = $this->schema->createTable('test_table', [
            'id' => 'pk',
            'name' => 'string not null',
            'description' => 'text',
        ]);

        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('test_table', $sql);

        $this->connection->createCommand($sql)->execute();
        $table = $this->schema->getTable('test_table');
        $this->assertNotNull($table);
    }

    // ---------------------------------------------------------------
    //  DROP TABLE
    // ---------------------------------------------------------------

    public function testDropTable(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_drop', [
            'id' => 'pk',
        ]))->execute();

        $this->assertNotNull($this->schema->getTable('test_drop'));

        $sql = $this->schema->dropTable('test_drop');
        $this->assertStringContainsString('DROP TABLE', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->schema->refresh();
        $this->assertNull($this->schema->getTable('test_drop'));
    }

    // ---------------------------------------------------------------
    //  RENAME TABLE
    // ---------------------------------------------------------------

    public function testRenameTable(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_rename_old', [
            'id' => 'pk',
        ]))->execute();

        $sql = $this->schema->renameTable('test_rename_old', 'test_rename_new');
        $this->assertStringContainsString('RENAME', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->schema->refresh();

        $this->assertNull($this->schema->getTable('test_rename_old'));
        $this->assertNotNull($this->schema->getTable('test_rename_new'));
    }

    // ---------------------------------------------------------------
    //  ADD COLUMN
    // ---------------------------------------------------------------

    public function testAddColumn(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_add_col', [
            'id' => 'pk',
        ]))->execute();

        $sql = $this->schema->addColumn('test_add_col', 'new_column', 'string');
        $this->assertStringContainsString('ADD', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->schema->refresh();

        $table = $this->schema->getTable('test_add_col');
        $this->assertArrayHasKey('new_column', $table->columns);
    }

    // ---------------------------------------------------------------
    //  DROP COLUMN
    // ---------------------------------------------------------------

    public function testDropColumn(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_drop_col', [
            'id' => 'pk',
            'to_drop' => 'string',
        ]))->execute();

        $sql = $this->schema->dropColumn('test_drop_col', 'to_drop');
        $this->assertStringContainsString('DROP COLUMN', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->schema->refresh();

        $table = $this->schema->getTable('test_drop_col');
        $this->assertArrayNotHasKey('to_drop', $table->columns);
    }

    // ---------------------------------------------------------------
    //  ALTER COLUMN
    // ---------------------------------------------------------------

    public function testAlterColumn(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_alter_col', [
            'id' => 'pk',
            'name' => 'string',
        ]))->execute();

        $sql = $this->schema->alterColumn('test_alter_col', 'name', 'text');
        $this->assertStringContainsString('ALTER', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->schema->refresh();

        $table = $this->schema->getTable('test_alter_col');
        $this->assertArrayHasKey('name', $table->columns);
    }

    // ---------------------------------------------------------------
    //  INDEXES
    // ---------------------------------------------------------------

    public function testCreateIndex(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_index', [
            'id' => 'pk',
            'name' => 'string',
        ]))->execute();

        $sql = $this->schema->createIndex('idx_name', 'test_index', 'name');
        $this->assertStringContainsString('CREATE INDEX', $sql);

        $this->connection->createCommand($sql)->execute();
        // Index created successfully if no exception
        $this->assertTrue(true);
    }

    public function testCreateUniqueIndex(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_unique_idx', [
            'id' => 'pk',
            'email' => 'string',
        ]))->execute();

        $sql = $this->schema->createIndex('idx_email', 'test_unique_idx', 'email', true);
        $this->assertStringContainsString('UNIQUE', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->assertTrue(true);
    }

    public function testDropIndex(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_drop_idx', [
            'id' => 'pk',
            'name' => 'string',
        ]))->execute();

        $this->connection->createCommand($this->schema->createIndex('idx_to_drop', 'test_drop_idx', 'name'))->execute();

        $sql = $this->schema->dropIndex('idx_to_drop', 'test_drop_idx');
        $this->assertStringContainsString('DROP INDEX', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    //  FOREIGN KEYS
    // ---------------------------------------------------------------

    public function testAddForeignKey(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_fk_parent', [
            'id' => 'pk',
        ]))->execute();

        $this->connection->createCommand($this->schema->createTable('test_fk_child', [
            'id' => 'pk',
            'parent_id' => 'integer',
        ]))->execute();

        $sql = $this->schema->addForeignKey('fk_test', 'test_fk_child', 'parent_id', 'test_fk_parent', 'id');
        $this->assertStringContainsString('FOREIGN KEY', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->assertTrue(true);
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
        $this->assertStringContainsString('DROP', $sql);
        $this->assertStringContainsString('FOREIGN KEY', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    //  PRIMARY KEYS
    // ---------------------------------------------------------------

    public function testAddPrimaryKey(): void
    {
        $this->connection->createCommand($this->schema->createTable('test_add_pk', [
            'id' => 'integer',
            'name' => 'string',
        ]))->execute();

        $sql = $this->schema->addPrimaryKey('pk_test', 'test_add_pk', 'id');
        $this->assertStringContainsString('PRIMARY KEY', $sql);

        $this->connection->createCommand($sql)->execute();
        $this->schema->refresh();

        $table = $this->schema->getTable('test_add_pk');
        $this->assertEquals('id', $table->primaryKey);
    }
}

<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\Db\Schema\DbColumnSchema;
use Yii1x\ActiveRecord\Db\Schema\DbSchema;
use Yii1x\ActiveRecord\Db\Schema\DbTableSchema;

abstract class AbstractDbSchemaTest extends AbstractDatabaseTest
{
    protected DbSchema $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = $this->connection->getSchema();
    }

    // ---------------------------------------------------------------
    //  Schema basics
    // ---------------------------------------------------------------

    public function testSchemaInstance(): void
    {
        $this->assertInstanceOf(DbSchema::class, $this->schema);
        $this->assertSame($this->connection, $this->schema->getDbConnection());
    }

    public function testGetTable(): void
    {
        $table = $this->schema->getTable('posts');
        $this->assertInstanceOf(DbTableSchema::class, $table);
        $this->assertEquals('posts', $table->name);
    }

    public function testGetTableNotExists(): void
    {
        $table = $this->schema->getTable('nonexistent_table');
        $this->assertNull($table);
    }

    public function testGetTableNames(): void
    {
        $tableNames = $this->schema->getTableNames();
        $this->assertIsArray($tableNames);
        $this->assertContains('users', $tableNames);
        $this->assertContains('posts', $tableNames);
        $this->assertContains('categories', $tableNames);
    }

    public function testQuoteTableName(): void
    {
        $quoted = $this->schema->quoteTableName('posts');
        $this->assertIsString($quoted);
        $this->assertNotEmpty($quoted);
    }

    public function testQuoteColumnName(): void
    {
        $quoted = $this->schema->quoteColumnName('id');
        $this->assertIsString($quoted);
        $this->assertNotEmpty($quoted);
    }

    // ---------------------------------------------------------------
    //  Table metadata
    // ---------------------------------------------------------------

    public function testTablePrimaryKey(): void
    {
        $table = $this->schema->getTable('posts');
        $this->assertEquals('id', $table->primaryKey);
    }

    public function testTableCompositePrimaryKey(): void
    {
        $table = $this->schema->getTable('orders');
        $this->assertIsArray($table->primaryKey);
        $this->assertEquals(['key1', 'key2'], $table->primaryKey);
    }

    public function testTableForeignKeys(): void
    {
        $table = $this->schema->getTable('posts');
        $this->assertIsArray($table->foreignKeys);
        $this->assertArrayHasKey('author_id', $table->foreignKeys);
        $this->assertEquals(['users', 'id'], $table->foreignKeys['author_id']);
    }

    public function testTableMultipleForeignKeys(): void
    {
        $table = $this->schema->getTable('comments');
        $this->assertArrayHasKey('post_id', $table->foreignKeys);
        $this->assertArrayHasKey('author_id', $table->foreignKeys);
        $this->assertEquals(['posts', 'id'], $table->foreignKeys['post_id']);
        $this->assertEquals(['users', 'id'], $table->foreignKeys['author_id']);
    }

    public function testTableCompositeForeignKey(): void
    {
        $table = $this->schema->getTable('items');
        $this->assertIsArray($table->foreignKeys);
        $this->assertArrayHasKey('col1', $table->foreignKeys);
        $this->assertArrayHasKey('col2', $table->foreignKeys);
    }

    public function testTableColumns(): void
    {
        $table = $this->schema->getTable('posts');
        $this->assertCount(5, $table->columns);
        $this->assertArrayHasKey('id', $table->columns);
        $this->assertArrayHasKey('title', $table->columns);
        $this->assertArrayHasKey('create_time', $table->columns);
        $this->assertArrayHasKey('author_id', $table->columns);
        $this->assertArrayHasKey('content', $table->columns);
    }

    public function testTableColumnNames(): void
    {
        $table = $this->schema->getTable('posts');
        $columnNames = $table->getColumnNames();
        $this->assertEquals(['id', 'title', 'create_time', 'author_id', 'content'], $columnNames);
    }

    public function testTableGetColumn(): void
    {
        $table = $this->schema->getTable('posts');
        $column = $table->getColumn('id');
        $this->assertInstanceOf(DbColumnSchema::class, $column);

        $nonExistent = $table->getColumn('nonexistent');
        $this->assertNull($nonExistent);
    }

    public function testTableNoPrimaryKey(): void
    {
        $table = $this->schema->getTable('types');
        $this->assertNull($table->primaryKey);
    }

    public function testTableNoForeignKeys(): void
    {
        $table = $this->schema->getTable('types');
        $this->assertEmpty($table->foreignKeys);
    }

    // ---------------------------------------------------------------
    //  Column metadata
    // ---------------------------------------------------------------

    public function testColumnPrimaryKey(): void
    {
        $table = $this->schema->getTable('posts');
        $idColumn = $table->getColumn('id');

        $this->assertTrue($idColumn->isPrimaryKey);
        $this->assertFalse($idColumn->isForeignKey);

        $titleColumn = $table->getColumn('title');
        $this->assertFalse($titleColumn->isPrimaryKey);
    }

    public function testColumnForeignKey(): void
    {
        $table = $this->schema->getTable('posts');
        $authorColumn = $table->getColumn('author_id');

        $this->assertTrue($authorColumn->isForeignKey);
        $this->assertFalse($authorColumn->isPrimaryKey);
    }

    public function testColumnTypes(): void
    {
        $table = $this->schema->getTable('types');

        $intCol = $table->getColumn('int_col');
        $this->assertEquals('integer', $intCol->type);

        $charCol = $table->getColumn('char_col');
        $this->assertEquals('string', $charCol->type);

        $floatCol = $table->getColumn('float_col');
        $this->assertContains($floatCol->type, ['double', 'string']);
    }

    public function testColumnAllowNull(): void
    {
        $table = $this->schema->getTable('posts');

        $idColumn = $table->getColumn('id');
        $this->assertFalse($idColumn->allowNull);

        $contentColumn = $table->getColumn('content');
        $this->assertTrue($contentColumn->allowNull);
    }

    public function testColumnSize(): void
    {
        $table = $this->schema->getTable('types');

        $charCol = $table->getColumn('char_col');
        $this->assertEquals(100, $charCol->size);
    }

    public function testColumnDefaultValues(): void
    {
        $table = $this->schema->getTable('types');

        $intCol2 = $table->getColumn('int_col2');
        $this->assertEquals(1, $intCol2->defaultValue);

        $charCol2 = $table->getColumn('char_col2');
        $this->assertEquals('something', $charCol2->defaultValue);
    }

    public function testAutoIncrementColumn(): void
    {
        $table = $this->schema->getTable('posts');
        $idColumn = $table->getColumn('id');

        $this->assertTrue($idColumn->autoIncrement);
    }

    // ---------------------------------------------------------------
    //  Schema refresh
    // ---------------------------------------------------------------

    public function testSchemaRefresh(): void
    {
        $table1 = $this->schema->getTable('posts');
        $this->assertNotNull($table1);

        $this->schema->refresh();

        $table2 = $this->schema->getTable('posts');
        $this->assertNotNull($table2);
        $this->assertNotSame($table1, $table2);
    }
}

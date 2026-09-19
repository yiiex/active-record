<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use PDO;
use PDOStatement;
use Yii1x\ActiveRecord\Db\DbDataReader;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestFetchClass;
use Yii1x\ActiveRecord\Exceptions\DbException;

abstract class AbstractDbCommandTest extends AbstractDatabaseTest
{
    // ---------------------------------------------------------------
    //  Text & connection
    // ---------------------------------------------------------------

    public function testGetText(): void
    {
        $sql = 'SELECT * FROM posts';
        $command = $this->connection->createCommand($sql);

        $this->assertSame($sql, $command->getText());
    }

    public function testGetTextOnEmptyCommand(): void
    {
        $command = $this->connection->createCommand();

        $this->assertSame('', $command->getText());
    }

    public function testSetText(): void
    {
        $command = $this->connection->createCommand('SELECT title FROM posts');
        $this->assertSame('post 1', $command->queryScalar());

        $command->setText('SELECT id FROM posts');

        $this->assertSame('SELECT id FROM posts', $command->getText());
        $this->assertEquals(1, $command->queryScalar());
    }

    public function testSetTextAppliesTablePrefix(): void
    {
        $originalPrefix = $this->connection->tablePrefix;

        try {
            $this->connection->tablePrefix = 'tbl_';

            $command = $this->connection->createCommand('SELECT * FROM {{posts}}');

            $this->assertSame('SELECT * FROM tbl_posts', $command->getText());
        } finally {
            $this->connection->tablePrefix = $originalPrefix;
        }
    }

    public function testGetConnection(): void
    {
        $command = $this->connection->createCommand('SELECT 1');

        $this->assertSame($this->connection, $command->getConnection());
    }

    // ---------------------------------------------------------------
    //  Prepare / cancel
    // ---------------------------------------------------------------

    public function testPrepare(): void
    {
        $command = $this->connection->createCommand('SELECT title FROM posts');

        $this->assertNull($command->getPdoStatement());

        $command->prepare();

        $this->assertInstanceOf(PDOStatement::class, $command->getPdoStatement());
        $this->assertSame('post 1', $command->queryScalar());
    }

    public function testCancel(): void
    {
        $command = $this->connection->createCommand('SELECT title FROM posts');
        $command->prepare();
        $this->assertInstanceOf(PDOStatement::class, $command->getPdoStatement());

        $command->cancel();

        $this->assertNull($command->getPdoStatement());
    }

    // ---------------------------------------------------------------
    //  Execute
    // ---------------------------------------------------------------

    public function testExecute(): void
    {
        $command = $this->connection->createCommand(
            "INSERT INTO comments(content, post_id, author_id) VALUES ('test comment', 1, 1)"
        );

        $this->assertSame(1, $command->execute());
        $this->assertSame(1, $command->execute());

        $count = $this->connection->createCommand(
            "SELECT COUNT(*) FROM comments WHERE content = 'test comment'"
        )->queryScalar();

        $this->assertEquals(2, $count);
    }

    public function testExecuteWithParams(): void
    {
        $command = $this->connection->createCommand(
            'INSERT INTO comments(content, post_id, author_id) VALUES (:content, :post_id, :author_id)'
        );

        $affected = $command->execute([
            ':content' => 'param comment',
            ':post_id' => 2,
            ':author_id' => 1,
        ]);

        $this->assertSame(1, $affected);

        $count = $this->connection->createCommand(
            "SELECT COUNT(*) FROM comments WHERE content = 'param comment'"
        )->queryScalar();

        $this->assertEquals(1, $count);
    }

    public function testExecuteInvalidSql(): void
    {
        $this->expectException(DbException::class);

        $this->connection->createCommand('bad SQL')->execute();
    }

    // ---------------------------------------------------------------
    //  Query
    // ---------------------------------------------------------------

    public function testQuery(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();
        $this->assertInstanceOf(DbDataReader::class, $reader);

        $command = $this->connection->createCommand('SELECT * FROM posts');
        $command->prepare();
        $this->assertInstanceOf(DbDataReader::class, $command->query());
    }

    public function testQueryInvalidSql(): void
    {
        $this->expectException(DbException::class);

        $this->connection->createCommand('bad SQL')->query();
    }

    // ---------------------------------------------------------------
    //  Parameter binding
    // ---------------------------------------------------------------

    public function testBindParam(): void
    {
        $title = 'test title';
        $createTime = '2000-01-01 10:00:00';

        $insert = $this->connection->createCommand(
            'INSERT INTO posts(title, create_time, author_id) VALUES (:title, :create_time, 1)'
        );
        $insert->bindParam(':title', $title);
        $insert->bindParam(':create_time', $createTime);
        $this->assertSame(1, $insert->execute());

        $select = $this->connection->createCommand('SELECT create_time FROM posts WHERE title = :title');
        $select->bindParam(':title', $title);

        // Just verify data was inserted correctly, don't test exact type comparison
        $result = $select->queryScalar();
        $this->assertStringStartsWith('2000-01-01', $result);
    }

    public function testBindParamWithTypes(): void
    {
        $intCol = 123;
        $charCol = 'abc';
        $floatCol = 1.23;
        $numericCol = '1.23';
        $boolCol = true;

        $command = $this->connection->createCommand(
            'INSERT INTO types (int_col, char_col, float_col, numeric_col, bool_col)
         VALUES (:int_col, :char_col, :float_col, :numeric_col, :bool_col)'
        );
        $command->bindParam(':int_col', $intCol, PDO::PARAM_INT);
        $command->bindParam(':char_col', $charCol);
        $command->bindParam(':float_col', $floatCol);
        $command->bindParam(':numeric_col', $numericCol);
        $command->bindParam(':bool_col', $boolCol, PDO::PARAM_BOOL);
        $this->assertSame(1, $command->execute());

        $row = $this->connection->createCommand('SELECT * FROM types')->queryRow();

        // int: loose compare because drivers return string or int
        $this->assertEquals($intCol, $row['int_col']);

        // CHAR: drivers pad with spaces to column width, so trim before compare
        $this->assertEquals($charCol, trim($row['char_col']));

        // DECIMAL: drivers return with trailing zeros ('1.230'), cast to float for comparison
        $this->assertEquals($floatCol, (float)$row['float_col']);
        $this->assertEquals((float)$numericCol, (float)$row['numeric_col']);

        // Boolean: driver-specific storage ('1'/'0', 't'/'f', 1/0), check truthiness
        $this->assertTrue((bool)$row['bool_col']);
    }

    public function testBindValue(): void
    {
        $insert = $this->connection->createCommand(
            'INSERT INTO comments(content, post_id, author_id) VALUES (:content, 1, 1)'
        );
        $insert->bindValue(':content', 'test comment');
        $this->assertSame(1, $insert->execute());

        $select = $this->connection->createCommand('SELECT post_id FROM comments WHERE content = :content');
        $select->bindValue(':content', 'test comment');
        $this->assertEquals(1, $select->queryScalar());
    }

    public function testBindValues(): void
    {
        $command = $this->connection->createCommand(
            'INSERT INTO comments(content, post_id, author_id) VALUES (:content, :post_id, :author_id)'
        );
        $command->bindValues([
            ':content' => 'bulk comment',
            ':post_id' => 3,
            ':author_id' => 2,
        ]);
        $this->assertSame(1, $command->execute());

        $count = $this->connection->createCommand(
            "SELECT COUNT(*) FROM comments WHERE content = 'bulk comment'"
        )->queryScalar();

        $this->assertEquals(1, $count);
    }

    // ---------------------------------------------------------------
    //  Fetching results
    // ---------------------------------------------------------------

    public function testQueryAll(): void
    {
        $rows = $this->connection->createCommand('SELECT * FROM posts')->queryAll();

        $this->assertCount(5, $rows);
        $this->assertEquals(3, $rows[2]['id']);
        $this->assertSame('post 3', $rows[2]['title']);
    }

    public function testQueryAllEmpty(): void
    {
        $rows = $this->connection->createCommand('SELECT * FROM posts WHERE id = 10')->queryAll();

        $this->assertSame([], $rows);
    }

    public function testQueryAllWithNumericKeys(): void
    {
        $rows = $this->connection->createCommand('SELECT id, title FROM posts')->queryAll(false);

        $this->assertEquals(1, $rows[0][0]);
        $this->assertSame('post 1', $rows[0][1]);
    }

    public function testQueryRow(): void
    {
        $row = $this->connection->createCommand('SELECT * FROM posts')->queryRow();
        $this->assertEquals(1, $row['id']);
        $this->assertSame('post 1', $row['title']);

        $command = $this->connection->createCommand('SELECT * FROM posts');
        $command->prepare();
        $row = $command->queryRow();
        $this->assertEquals(1, $row['id']);
    }

    public function testQueryRowEmpty(): void
    {
        $this->assertFalse(
            $this->connection->createCommand('SELECT * FROM posts WHERE id = 10')->queryRow()
        );
    }

    public function testQueryRowInvalidSql(): void
    {
        $this->expectException(DbException::class);

        $this->connection->createCommand('bad SQL')->queryRow();
    }

    public function testQueryColumn(): void
    {
        $column = $this->connection->createCommand('SELECT id FROM posts')->queryColumn();

        $this->assertEquals(range(1, 5), $column);
    }

    public function testQueryColumnEmpty(): void
    {
        $this->assertSame(
            [],
            $this->connection->createCommand('SELECT id FROM posts WHERE id = 10')->queryColumn()
        );
    }

    public function testQueryScalar(): void
    {
        $this->assertEquals(1, $this->connection->createCommand('SELECT id FROM posts')->queryScalar());

        $command = $this->connection->createCommand('SELECT id FROM posts');
        $command->prepare();
        $this->assertEquals(1, $command->queryScalar());
    }

    public function testQueryScalarEmpty(): void
    {
        $this->assertFalse(
            $this->connection->createCommand('SELECT id FROM posts WHERE id = 10')->queryScalar()
        );
    }

    public function testQueryScalarInvalidSql(): void
    {
        $this->expectException(DbException::class);

        $this->connection->createCommand('bad SQL')->queryScalar();
    }

    // ---------------------------------------------------------------
    //  Fetch modes
    // ---------------------------------------------------------------

    public function testFetchModeDefault(): void
    {
        $row = $this->connection->createCommand('SELECT * FROM posts')->queryRow();

        $this->assertIsArray($row);
    }

    public function testFetchModeObject(): void
    {
        $row = $this->connection->createCommand('SELECT * FROM posts')
            ->setFetchMode(PDO::FETCH_OBJ)
            ->queryRow();

        $this->assertIsObject($row);
        $this->assertSame('post 1', $row->title);
    }

    public function testFetchModeClass(): void
    {
        $row = $this->connection->createCommand('SELECT * FROM posts')
            ->setFetchMode(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, TestFetchClass::class)
            ->queryRow();

        $this->assertInstanceOf(TestFetchClass::class, $row);
        $this->assertSame('post 1', $row->title);
    }

    // ---------------------------------------------------------------
    //  Reset
    // ---------------------------------------------------------------

    public function testReset(): void
    {
        $command = $this->connection->createCommand('SELECT * FROM posts');
        $command->prepare();

        $command->reset();

        $this->assertNull($command->getPdoStatement());
        $this->assertSame('', $command->getText());
        $this->assertSame([], $command->params);
    }

    // ---------------------------------------------------------------
    //  Query builder (behavioral tests, driver-agnostic)
    // ---------------------------------------------------------------

    public function testQueryBuilderSelectFromWhere(): void
    {
        $row = $this->connection->createCommand()
            ->select('id, title')
            ->from('posts')
            ->where('id = :id', [':id' => 2])
            ->queryRow();

        $this->assertEquals(2, $row['id']);
        $this->assertSame('post 2', $row['title']);
    }

    public function testQueryBuilderOrderLimitOffset(): void
    {
        $rows = $this->connection->createCommand()
            ->select('id')
            ->from('posts')
            ->order('id DESC')
            ->limit(2, 1)
            ->queryColumn();

        $this->assertEquals([4, 3], $rows);
    }

    public function testQueryBuilderJoin(): void
    {
        $count = $this->connection->createCommand()
            ->select('COUNT(*)')
            ->from('posts')
            ->join('comments', 'comments.post_id = posts.id')
            ->where('posts.id = :id', [':id' => 1])
            ->queryScalar();

        $this->assertEquals(3, $count);
    }

    public function testQueryBuilderGroupHaving(): void
    {
        $rows = $this->connection->createCommand()
            ->select('author_id, COUNT(*) AS total')
            ->from('posts')
            ->group('author_id')
            ->having('COUNT(*) >= 3')
            ->queryAll();

        $this->assertCount(1, $rows);
        $this->assertEquals(2, $rows[0]['author_id']);
        $this->assertEquals(3, $rows[0]['total']);
    }

    public function testQueryBuilderUnion(): void
    {
        $column = $this->connection->createCommand()
            ->select('id')
            ->from('posts')
            ->where('id = 1')
            ->union('SELECT id FROM posts WHERE id = 2')
            ->queryColumn();

        $this->assertEquals([1, 2], $column);
    }
}

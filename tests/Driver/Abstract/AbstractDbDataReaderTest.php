<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use PDO;
use Yii1x\ActiveRecord\Db\DbConnection;
use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\ActiveRecord\Tests\Infrastructure\PostRecord;

abstract class AbstractDbDataReaderTest extends AbstractDatabaseTest
{
    // ---------------------------------------------------------------
    //  Basic reading
    // ---------------------------------------------------------------

    public function testRead(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();

        for ($i = 1; $i <= 5; ++$i) {
            $row = $reader->read();
            $this->assertEquals($i, $row['id']);
        }

        $this->assertFalse($reader->read());
    }

    public function testReadColumn(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();

        // Read column 0 (id) from row 1
        $this->assertEquals(1, $reader->readColumn(0));

        // Read column 1 (title) from row 2
        $this->assertSame('post 2', $reader->readColumn(1));

        // Skip to row 5
        $reader->readColumn(0);
        $reader->readColumn(0);

        $this->assertEquals(5, $reader->readColumn(0));
        $this->assertFalse($reader->readColumn(0));
    }

    public function testReadObject(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();
        $object = $reader->readObject(PostRecord::class, [null, 'v2']);

        $this->assertInstanceOf(PostRecord::class, $object);
        $this->assertEquals(1, $object->id);
        $this->assertSame('post 1', $object->title);
        $this->assertNull($object->param1);
        $this->assertSame('v2', $object->param2);
    }

    public function testReadAll(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();
        $rows = $reader->readAll();

        $this->assertCount(5, $rows);
        $this->assertEquals(3, $rows[2]['id']);
        $this->assertSame('post 3', $rows[2]['title']);
    }

    public function testReadAllEmpty(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts WHERE id = 10')->query();
        $this->assertSame([], $reader->readAll());
    }

    // ---------------------------------------------------------------
    //  Close / state
    // ---------------------------------------------------------------

    public function testClose(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();
        $reader->read();
        $reader->read();

        $this->assertFalse($reader->getIsClosed());

        $reader->close();

        $this->assertTrue($reader->getIsClosed());
    }

    // ---------------------------------------------------------------
    //  Counts
    // ---------------------------------------------------------------

    public function testColumnCount(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();
        $this->assertEquals(7, $reader->getColumnCount());

        $reader2 = $this->connection->createCommand('SELECT * FROM posts WHERE id = 11')->query();
        $this->assertEquals(7, $reader2->getColumnCount());
    }

    // ---------------------------------------------------------------
    //  Iterator (foreach)
    // ---------------------------------------------------------------

    public function testForeach(): void
    {
        $ids = [];
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();

        foreach ($reader as $row) {
            $ids[] = $row['id'];
        }

        $this->assertCount(5, $ids);
        $this->assertEquals(4, $ids[3]);

        $this->expectException(DbException::class);
        foreach ($reader as $row) {
            $ids[] = $row['id'];
        }
    }

    // ---------------------------------------------------------------
    //  Fetch mode
    // ---------------------------------------------------------------

    public function testFetchModeNumeric(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();
        $reader->setFetchMode(PDO::FETCH_NUM);
        $row = $reader->read();

        $this->assertFalse(isset($row['id']));
        $this->assertTrue(isset($row[0]));
        $this->assertEquals(1, $row[0]);
    }

    public function testFetchModeAssoc(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();
        $reader->setFetchMode(PDO::FETCH_NUM);
        $reader->read(); // consume first row

        $reader->setFetchMode(PDO::FETCH_ASSOC);
        $row = $reader->read();

        $this->assertTrue(isset($row['id']));
        $this->assertFalse(isset($row[0]));
    }

    // ---------------------------------------------------------------
    //  Bind column
    // ---------------------------------------------------------------

    public function testBindColumn(): void
    {
        $reader = $this->connection->createCommand('SELECT * FROM posts')->query();
        $id = null;
        $title = null;

        $reader->bindColumn(1, $id);
        $reader->bindColumn(2, $title);

        $reader->read();
        $this->assertEquals(1, $id);
        $this->assertSame('post 1', $title);

        $reader->read();
        $this->assertEquals(2, $id);
        $this->assertSame('post 2', $title);
    }

    public function testBindColumnByName(): void
    {
        $reader = $this->connection->createCommand('SELECT id, title FROM posts')->query();
        $id = null;
        $title = null;

        $reader->bindColumn('id', $id);
        $reader->bindColumn('title', $title);

        $reader->read();
        $this->assertEquals(1, $id);
        $this->assertSame('post 1', $title);
    }
}

<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\Db\DbConnection;

abstract class AbstractDbTransactionTest extends AbstractDatabaseTest
{
    protected DbConnection $connection;

    abstract protected function driverName(): string;

    protected function setUp(): void
    {
        $this->connection = $this->databaseFactory($this->driverName());
        $this->populateDatabase($this->connection);
    }

    protected function tearDown(): void
    {
        if ($this->connection && $this->connection->getActive()) {
            // Rollback any active transaction
            $transaction = $this->connection->getCurrentTransaction();
            if ($transaction && $transaction->getActive()) {
                $transaction->rollback();
            }
            $this->connection->setActive(false);
        }
    }

    public function testBeginTransaction(): void
    {
        $sql = "INSERT INTO posts(id, title, create_time, author_id) VALUES(10, 'test post', '2000-01-01', 1)";
        $transaction = $this->connection->beginTransaction();

        try {
            $this->connection->createCommand($sql)->execute();
            $this->connection->createCommand($sql)->execute(); // Should fail (duplicate ID)
            $this->fail('Expected exception not raised');
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollback();
            $reader = $this->connection->createCommand('SELECT * FROM posts WHERE id=10')->query();
            $this->assertFalse($reader->read());
        }
    }

    public function testCommit(): void
    {
        $sql = "INSERT INTO posts(id, title, create_time, author_id) VALUES(10, 'test post', '2000-01-01', 1)";
        $transaction = $this->connection->beginTransaction();

        try {
            $this->connection->createCommand($sql)->execute();
            $this->assertTrue($transaction->getActive());
            $transaction->commit();
            $this->assertFalse($transaction->getActive());
        } catch (\Exception $e) {
            $transaction->rollback();
            $this->fail('Unexpected exception');
        }

        $n = $this->connection->createCommand('SELECT COUNT(*) FROM posts WHERE id=10')->queryScalar();
        $this->assertEquals(1, $n);
    }

    public function testRollback(): void
    {
        $sql = "INSERT INTO posts(id, title, create_time, author_id) VALUES(20, 'test post', '2000-01-01', 1)";
        $transaction = $this->connection->beginTransaction();

        $this->connection->createCommand($sql)->execute();
        $this->assertTrue($transaction->getActive());

        $transaction->rollback();
        $this->assertFalse($transaction->getActive());

        $n = $this->connection->createCommand('SELECT COUNT(*) FROM posts WHERE id=20')->queryScalar();
        $this->assertEquals(0, $n);
    }

    public function testGetActive(): void
    {
        $transaction = $this->connection->beginTransaction();

        $this->assertTrue($transaction->getActive());

        $transaction->commit();

        $this->assertFalse($transaction->getActive());
    }

    public function testGetDbConnection(): void
    {
        $transaction = $this->connection->beginTransaction();

        $this->assertSame($this->connection, $transaction->getConnection());

        $transaction->rollback();
    }

    public function testGetCurrentTransaction(): void
    {
        $this->assertNull($this->connection->getCurrentTransaction());

        $transaction = $this->connection->beginTransaction();

        $this->assertSame($transaction, $this->connection->getCurrentTransaction());

        $transaction->commit();

        $this->assertNull($this->connection->getCurrentTransaction());
    }

    public function testDoubleCommitThrowsException(): void
    {
        $this->expectException(\Exception::class);

        $transaction = $this->connection->beginTransaction();
        $transaction->commit();
        $transaction->commit(); // Should throw exception
    }

    public function testDoubleRollbackThrowsException(): void
    {
        $this->expectException(\Exception::class);

        $transaction = $this->connection->beginTransaction();
        $transaction->rollback();
        $transaction->rollback(); // Should throw exception
    }
}

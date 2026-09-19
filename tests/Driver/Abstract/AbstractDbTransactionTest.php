<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\Exceptions\DbException;

abstract class AbstractDbTransactionTest extends AbstractDatabaseTest
{
    protected bool $useTransaction = false;

    public function testBeginTransaction(): void
    {
        $sql = "INSERT INTO posts(id, title, create_time, author_id) VALUES(10, 'test post', '2000-01-01', 1)";
        $transaction = $this->connection->beginTransaction();

        $this->connection->createCommand($sql)->execute();

        $duplicateRaised = false;
        try {
            $this->connection->createCommand($sql)->execute(); // duplicate ID
        } catch (DbException) {
            $duplicateRaised = true;
        }

        $transaction->rollback();

        $this->assertTrue($duplicateRaised, 'Inserting a duplicate primary key should throw');
        $reader = $this->connection->createCommand('SELECT * FROM posts WHERE id=10')->query();
        $this->assertFalse($reader->read());
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
        } catch (\Throwable $e) {
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

<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use PDO;
use Yii1x\ActiveRecord\Db\DbConnection;

abstract class AbstractDbConnectionTest extends AbstractDatabaseTest
{
    public function testAutoConnectEnabledByDefault(): void
    {
        $connection = $this->databaseFactory($this->driverName());

        $this->assertTrue($connection->autoConnect);
        $this->assertTrue($connection->getActive());
        $this->assertInstanceOf(PDO::class, $connection->getPdoInstance());
        $connection->setActive(false);
    }

    public function testAutoConnectDisabled(): void
    {
        $connection = $this->databaseFactory($this->driverName(), false);

        $this->assertFalse($connection->autoConnect);
        $this->assertFalse($connection->getActive());
        $this->assertNull($connection->getPdoInstance());

        $connection->setActive(true);

        $this->assertTrue($connection->getActive());
        $this->assertInstanceOf(PDO::class, $connection->getPdoInstance());
        $connection->setActive(false);
    }

    public function testSetActive(): void
    {
        $connection = $this->databaseFactory($this->driverName(), false);

        $this->assertFalse($connection->getActive());

        $connection->setActive(true);

        $this->assertTrue($connection->getActive());
        $pdo = $connection->getPdoInstance();
        $this->assertInstanceOf(PDO::class, $pdo);

        $connection->setActive(true);
        $this->assertSame($pdo, $connection->getPdoInstance());

        $connection->setActive(false);

        $this->assertFalse($connection->getActive());
        $this->assertNull($connection->getPdoInstance());
    }

    public function testSetActiveWithInvalidDriver(): void
    {
        $this->expectException(\Exception::class);

        $connection = new DbConnection(
            'unknown::memory:',
            '',
            '',
            'test',
            false
        );

        $connection->setActive(true);
    }

    // ---------------------------------------------------------------
    //  Command creation
    // ---------------------------------------------------------------

    public function testCreateCommand(): void
    {
        $sql = 'SELECT * FROM posts';
        $command = $this->connection->createCommand($sql);

        $this->assertSame($sql, $command->getText());
        $this->assertSame($this->connection, $command->getConnection());
    }

    // ---------------------------------------------------------------
    //  Last Insert ID
    // ---------------------------------------------------------------

    public function testLastInsertID(): void
    {
        $maxBefore = (int)$this->connection->createCommand('SELECT MAX(id) FROM posts')->queryScalar();

        $sql = "INSERT INTO posts(title,create_time,author_id) VALUES('test post','2000-01-01',1)";
        $this->connection->createCommand($sql)->execute();

        $this->assertEquals($maxBefore + 1, (int)$this->connection->getLastInsertID());
    }

    // ---------------------------------------------------------------
    //  Value quoting
    // ---------------------------------------------------------------

    public function testQuoteValue(): void
    {
        $str = "this is 'my' name";
        $expected = "'this is ''my'' name'";

        $this->assertSame($expected, $this->connection->quoteValue($str));
    }

    // ---------------------------------------------------------------
    //  PDO attributes
    // ---------------------------------------------------------------

    public function testColumnNameCase(): void
    {
        $default = $this->connection->getColumnCase();

        try {
            $this->assertSame(PDO::CASE_NATURAL, $default);
            $this->connection->setColumnCase(PDO::CASE_LOWER);
            $this->assertSame(PDO::CASE_LOWER, $this->connection->getColumnCase());
        } finally {
            $this->connection->setColumnCase($default);
        }
    }

    public function testNullConversion(): void
    {
        $default = $this->connection->getNullConversion();

        try {
            $this->assertSame(PDO::NULL_NATURAL, $default);
            $this->connection->setNullConversion(PDO::NULL_EMPTY_STRING);
            $this->assertSame(PDO::NULL_EMPTY_STRING, $this->connection->getNullConversion());
        } finally {
            $this->connection->setNullConversion($default);
        }
    }
}

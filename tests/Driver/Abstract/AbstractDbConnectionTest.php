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
    //  Создание команд
    // ---------------------------------------------------------------

    public function testCreateCommand(): void
    {
        $sql = 'SELECT * FROM posts';
        $command = $this->connection->createCommand($sql);
        $this->assertNotNull($command);
    }

    // ---------------------------------------------------------------
    //  Last Insert ID
    // ---------------------------------------------------------------

    public function testLastInsertID(): void
    {
        $sql = "INSERT INTO posts(title,create_time,author_id) VALUES('test post','2000-01-01',1)";
        $this->connection->createCommand($sql)->execute();

        $this->assertEquals(6, $this->connection->getLastInsertID());
    }

    // ---------------------------------------------------------------
    //  Экранирование значений
    // ---------------------------------------------------------------

    public function testQuoteValue(): void
    {
        $str = "this is 'my' name";
        $expected = "'this is ''my'' name'";

        $this->assertSame($expected, $this->connection->quoteValue($str));
    }

    // ---------------------------------------------------------------
    //  PDO атрибуты
    // ---------------------------------------------------------------

    public function testColumnNameCase(): void
    {
        $this->assertEquals(PDO::CASE_NATURAL, $this->connection->getColumnCase());
        $this->connection->setColumnCase(PDO::CASE_LOWER);
        $this->assertEquals(PDO::CASE_LOWER, $this->connection->getColumnCase());
    }

    public function testNullConversion(): void
    {
        $this->assertEquals(PDO::NULL_NATURAL, $this->connection->getNullConversion());
        $this->connection->setNullConversion(PDO::NULL_EMPTY_STRING);
        $this->assertEquals(PDO::NULL_EMPTY_STRING, $this->connection->getNullConversion());
    }
}

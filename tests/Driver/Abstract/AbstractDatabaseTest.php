<?php

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Yii1x\ActiveRecord\Db\DbConnection;

abstract class AbstractDatabaseTest extends TestCase
{
    protected ContainerInterface $container {
        get => $this->container ??= new TestContainer();
    }

    protected function databaseFactory(string $name, bool $autoConnect = true): DbConnection
    {
        return match ($name) {
            'mysql' => new DbConnection(
                getenv('MYSQL_DSN'),
                getenv('MYSQL_USER'),
                getenv('MYSQL_PASSWORD'),
                'mysql',
                $autoConnect,
            ),
            'pgsql' => new DbConnection(
                getenv('PGSQL_DSN'),
                getenv('PGSQL_USER'),
                getenv('PGSQL_PASSWORD'),
                'pgsql',
                $autoConnect,
            ),
            'sqlite' => new DbConnection(
                getenv('SQLITE_DSN'),
                '',
                '',
                'sqlite',
                $autoConnect,
            ),
        };
    }

    protected function populateDatabase(DbConnection $connection): void
    {
        $driver = $connection->getDriverName();
        $schemaFile = __DIR__ . "/../../_data/{$driver}.sql";
        if (!file_exists($schemaFile)) {
            throw new \RuntimeException("Schema file not found: {$schemaFile}");
        }
        $pdo = $connection->getPdoInstance();
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
    }
}

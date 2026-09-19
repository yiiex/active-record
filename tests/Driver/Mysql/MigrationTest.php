<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Mysql;

use Yii1x\ActiveRecord\Exceptions\MigrationException;
use Yii1x\ActiveRecord\Migration\MigrationManager;
use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractMigrationTest;

class MigrationTest extends AbstractMigrationTest
{
    protected function driverName(): string
    {
        return 'mysql';
    }

    /**
     * MySQL implicitly commits DDL, so a migration that fails after a DDL statement
     * cannot be rolled back and stays applied without a history entry.
     */
    public function testPartialDdlIsNotRolledBack(): void
    {
        $this->writeMigration(
            'm250101_000001_a',
            "\$this->createTable('mig_a', ['id' => 'pk']);\nthrow new \\RuntimeException('boom');"
        );

        try {
            $this->manager->up();
            $this->fail('MigrationException was expected');
        } catch (MigrationException) {
            // expected
        }

        $this->assertTrue($this->tableExists('mig_a'));
        $this->assertSame([MigrationManager::BASE_MIGRATION], $this->historyNames());
    }
}

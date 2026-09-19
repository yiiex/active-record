<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Pgsql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractMigrationTest;

class MigrationTest extends AbstractMigrationTest
{
    protected function driverName(): string
    {
        return 'pgsql';
    }
}

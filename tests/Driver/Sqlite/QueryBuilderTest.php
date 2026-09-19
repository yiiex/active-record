<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Sqlite;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractQueryBuilderTest;

class QueryBuilderTest extends AbstractQueryBuilderTest
{
    protected function driverName(): string
    {
        return 'sqlite';
    }
}

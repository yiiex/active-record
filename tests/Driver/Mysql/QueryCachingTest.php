<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Mysql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractQueryCachingTest;

class QueryCachingTest extends AbstractQueryCachingTest
{
    protected function driverName(): string
    {
        return 'mysql';
    }
}

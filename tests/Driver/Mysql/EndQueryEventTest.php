<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Mysql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractEndQueryEventTest;

class EndQueryEventTest extends AbstractEndQueryEventTest
{
    protected function driverName(): string
    {
        return 'mysql';
    }
}

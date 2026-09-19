<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Pgsql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractEndQueryEventTest;

class EndQueryEventTest extends AbstractEndQueryEventTest
{
    protected function driverName(): string
    {
        return 'pgsql';
    }
}

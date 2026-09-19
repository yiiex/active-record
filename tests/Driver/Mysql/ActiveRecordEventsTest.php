<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Mysql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractActiveRecordEventsTest;

class ActiveRecordEventsTest extends AbstractActiveRecordEventsTest
{
    protected function driverName(): string
    {
        return 'mysql';
    }
}

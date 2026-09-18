<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Pgsql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractActiveRecordEventsTest;

class ActiveRecordEventsTest extends AbstractActiveRecordEventsTest
{
    protected function driverName(): string
    {
        return 'pgsql';
    }
}

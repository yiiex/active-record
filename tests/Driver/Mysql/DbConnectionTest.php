<?php

namespace Yii1x\ActiveRecord\Tests\Driver\Mysql;

use Yii1x\ActiveRecord\Tests\Driver\Abstract\AbstractDbConnectionTest;

class DbConnectionTest extends AbstractDbConnectionTest
{

    protected function driverName(): string
    {
        return 'mysql';
    }

    public function testQuoteValue(): void
    {
        $str = "this is 'my' name";
        // MySQL escapes single quotes with backslash
        $expected = "'this is \\'my\\' name'";

        $this->assertSame($expected, $this->connection->quoteValue($str));
    }
}

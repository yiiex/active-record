<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Attributes\Database;
use Yii1x\ActiveRecord\Attributes\Table;

#[Database('alpha')]
#[Table('attr_alpha_table')]
class AttrBoth extends ActiveRecord
{
}

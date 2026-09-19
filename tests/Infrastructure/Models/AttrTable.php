<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Attributes\Table;

#[Table('attr_custom_table')]
class AttrTable extends ActiveRecord
{
}

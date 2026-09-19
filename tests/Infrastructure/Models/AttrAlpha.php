<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Attributes\Database;

#[Database('alpha')]
class AttrAlpha extends ActiveRecord
{
}

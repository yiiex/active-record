<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\Db\Schema\DbCriteria;

/**
 * Post model with an implicit default scope.
 */
class PostWithDefaultScope extends Post
{
    public function defaultScope(): DbCriteria
    {
        return (new DbCriteria())->compare('author_id', 2);
    }

    public function desc(): static
    {
        $this->getDbCriteria()->order = 'id DESC';
        return $this;
    }
}

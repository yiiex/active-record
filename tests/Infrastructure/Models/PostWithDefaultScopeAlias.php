<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\Db\Schema\DbCriteria;

/**
 * Post model whose default scope resolves the table alias without scope
 * recursion (getTableAlias(false, false)).
 */
class PostWithDefaultScopeAlias extends Post
{
    public function defaultScope(): DbCriteria
    {
        $alias = $this->getTableAlias(false, false);
        return (new DbCriteria())->compare($alias . '.author_id', 2);
    }
}

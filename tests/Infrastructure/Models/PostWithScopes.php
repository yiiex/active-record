<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure\Models;

use Yii1x\ActiveRecord\Db\Schema\DbCriteria;

/**
 * Post model exposing named scopes for scope tests.
 */
class PostWithScopes extends Post
{
    public function scopes(): array
    {
        $alias = $this->getTableAlias(true);

        $recent = new DbCriteria();
        $recent->order = $alias . '.id DESC';

        return [
            'post23' => (new DbCriteria())
                ->compare($alias . '.id', '>=2')
                ->compare($alias . '.id', '<=3'),
            'recent' => $recent,
        ];
    }

    public function byAuthor(int $authorId): static
    {
        $this->getDbCriteria()
            ->compare($this->getTableAlias(true) . '.author_id', $authorId);
        return $this;
    }

    public function after(int $id): static
    {
        $this->getDbCriteria()
            ->compare($this->getTableAlias(true) . '.id', '>' . $id);
        return $this;
    }
}

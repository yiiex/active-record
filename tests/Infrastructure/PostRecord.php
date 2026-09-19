<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure;

class PostRecord
{
    public int|string $id;
    public ?string $title = null;
    public ?string $content = null;
    public ?string $create_time = null;
    public int|string $author_id;
    public int $view_count = 0;

    public function __construct(public ?string $param1, public ?string $param2)
    {
    }
}

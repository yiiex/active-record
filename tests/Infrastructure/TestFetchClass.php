<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Infrastructure;

class TestFetchClass
{
    public int|string $id;
    public string $title;
    public string $create_time;
    public int|string $author_id;
    public ?string $content = null;
    public int $view_count = 0;
    public int $rating = 0;
}

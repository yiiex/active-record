<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Psr\SimpleCache\CacheInterface;
use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\ORMContext;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Post;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestCache;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestContainer;

abstract class AbstractQueryCachingTest extends AbstractDatabaseTest
{
    protected TestCache $cache;

    public static function setUpBeforeClass(): void
    {
        Post::model()->refreshMetaData();
    }

    protected function setUp(): void
    {
        parent::setUp();
        ActiveRecord::$db = $this->connection;

        $this->cache = new TestCache();
        $container = new TestContainer();
        $container->set(CacheInterface::class, $this->cache);
        ORMContext::bootstrap($container);
    }

    protected function tearDown(): void
    {
        ORMContext::bootstrap(new TestContainer());
        ActiveRecord::$db = null;
        parent::tearDown();
    }

    public function testQueryResultIsCached(): void
    {
        $this->connection->cache(60, 2);

        $first = $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();
        $this->assertSame('post 1', $first);

        $this->connection->createCommand("UPDATE posts SET title = 'changed' WHERE id = 1")->execute();

        // Served from cache although the row changed
        $second = $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();
        $this->assertSame('post 1', $second);
        $this->assertSame(1, $this->cache->hits);

        // Cache budget exhausted, hits the database again
        $third = $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();
        $this->assertSame('changed', $third);
    }

    public function testQueryCachingDisabledByDefault(): void
    {
        $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();

        $this->connection->createCommand("UPDATE posts SET title = 'changed' WHERE id = 1")->execute();

        $second = $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();
        $this->assertSame('changed', $second);
        $this->assertSame(0, $this->cache->setCount);
    }

    public function testCacheDurationZeroDisables(): void
    {
        $this->connection->cache(0, 5);

        $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();
        $this->connection->createCommand("UPDATE posts SET title = 'changed' WHERE id = 1")->execute();
        $second = $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();

        $this->assertSame('changed', $second);
        $this->assertSame(0, $this->cache->setCount);
    }

    public function testDifferentParamsAreCachedSeparately(): void
    {
        $this->connection->cache(60, 4);

        $this->assertSame(
            'post 1',
            $this->connection->createCommand('SELECT title FROM posts WHERE id = :id')->queryScalar([':id' => 1])
        );
        $this->assertSame(
            'post 2',
            $this->connection->createCommand('SELECT title FROM posts WHERE id = :id')->queryScalar([':id' => 2])
        );
        $this->assertSame(2, $this->cache->setCount);

        $this->assertSame(
            'post 1',
            $this->connection->createCommand('SELECT title FROM posts WHERE id = :id')->queryScalar([':id' => 1])
        );
        $this->assertSame(
            'post 2',
            $this->connection->createCommand('SELECT title FROM posts WHERE id = :id')->queryScalar([':id' => 2])
        );
        $this->assertSame(2, $this->cache->hits);
    }

    public function testActiveRecordCacheShortcut(): void
    {
        $first = Post::model()->cache(60, 2)->findByPk(2);
        $this->assertNotNull($first);
        $title = $first->title;

        $this->connection->createCommand("UPDATE posts SET title = 'changed' WHERE id = 2")->execute();

        $second = Post::model()->findByPk(2);
        $this->assertNotNull($second);
        $this->assertSame($title, $second->title);
    }

    public function testSchemaCachingAndRefreshInvalidation(): void
    {
        $this->connection->schemaCachingDuration = 60;
        $schema = $this->connection->getSchema();
        $schema->refresh();

        $table = $schema->getTable('posts');
        $this->assertNotNull($table);
        $this->assertGreaterThanOrEqual(1, $this->cache->setCount);
        $this->assertStringContainsString('yii#dbschema', $this->cache->setKeys[0]);

        $deletedBefore = $this->cache->deleteCount;
        $schema->refresh();
        $this->assertGreaterThan($deletedBefore, $this->cache->deleteCount);
    }

    public function testCachingWithoutCacheServiceDoesNotThrow(): void
    {
        ORMContext::bootstrap(new TestContainer());

        $this->connection->cache(60, 2);

        $title = $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();

        $this->assertSame('post 1', $title);
    }

    public function testQueryCacheIdResolvesNamedService(): void
    {
        $named = new TestCache();
        $container = new TestContainer();
        $container->set(CacheInterface::class, $this->cache);
        $container->set('queryCache', $named);
        ORMContext::bootstrap($container);

        $this->connection->queryCacheID = 'queryCache';
        $this->connection->cache(60, 1);

        $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();

        $this->assertSame(1, $named->setCount);
        $this->assertSame(0, $this->cache->setCount);
    }

    public function testQueryCacheIdNullDisablesCaching(): void
    {
        $this->connection->queryCacheID = null;
        $this->connection->cache(60, 2);

        $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();
        $this->connection->createCommand("UPDATE posts SET title = 'changed' WHERE id = 1")->execute();
        $second = $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();

        $this->assertSame('changed', $second);
        $this->assertSame(0, $this->cache->setCount);
    }

    public function testUnknownQueryCacheIdDisablesCachingGracefully(): void
    {
        $this->connection->queryCacheID = 'missingCache';
        $this->connection->cache(60, 2);

        $title = $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();

        $this->assertSame('post 1', $title);
        $this->assertSame(0, $this->cache->setCount);
    }

    public function testWrongTypeCacheServiceThrows(): void
    {
        $container = new TestContainer();
        $container->set('badCache', new \stdClass());
        ORMContext::bootstrap($container);

        $this->connection->queryCacheID = 'badCache';
        $this->connection->cache(60, 2);

        $this->expectException(\RuntimeException::class);
        $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();
    }

    public function testSchemaCacheIdResolvesNamedService(): void
    {
        $named = new TestCache();
        $container = new TestContainer();
        $container->set('schemaCache', $named);
        ORMContext::bootstrap($container);

        $this->connection->schemaCacheID = 'schemaCache';
        $this->connection->schemaCachingDuration = 60;

        $schema = $this->connection->getSchema();
        $schema->refresh();
        $schema->getTable('posts');

        $this->assertGreaterThanOrEqual(1, $named->setCount);
    }
}

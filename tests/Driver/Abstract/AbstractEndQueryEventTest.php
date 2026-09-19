<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Psr\EventDispatcher\EventDispatcherInterface;
use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Events\EndQueryEvent;
use Yii1x\ActiveRecord\ORMContext;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Post;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestContainer;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestEventDispatcher;

abstract class AbstractEndQueryEventTest extends AbstractDatabaseTest
{
    protected TestEventDispatcher $dispatcher;

    public static function setUpBeforeClass(): void
    {
        Post::model()->refreshMetaData();
    }

    protected function setUp(): void
    {
        parent::setUp();
        ActiveRecord::$db = $this->connection;

        $this->dispatcher = new TestEventDispatcher();
        ORMContext::bootstrap($this->containerWithDispatcher(), profile: true);
    }

    protected function tearDown(): void
    {
        ORMContext::bootstrap(new TestContainer());
        ActiveRecord::$db = null;
        parent::tearDown();
    }

    private function containerWithDispatcher(): TestContainer
    {
        $container = new TestContainer();
        $container->set(EventDispatcherInterface::class, $this->dispatcher);

        return $container;
    }

    public function testEventDispatchedOnSelect(): void
    {
        $result = $this->connection->createCommand('SELECT id FROM posts WHERE id = :id')->queryScalar([':id' => 1]);
        $this->assertEquals(1, $result);

        $events = $this->dispatcher->getEvents(EndQueryEvent::class);
        $this->assertCount(1, $events);

        $event = $events[0];
        $this->assertStringContainsString('SELECT id FROM posts WHERE id = :id', $event->sql);
        $this->assertSame([':id' => 1], $event->params);
        $this->assertSame($this->connection, $event->connection);
        $this->assertSame($this->driverName(), $event->connectionName);
        $this->assertNotSame('', $event->queryId);
        $this->assertIsFloat($event->duration);
        $this->assertGreaterThanOrEqual(0.0, $event->duration);
    }

    public function testEventDispatchedOnExecute(): void
    {
        $this->connection
            ->createCommand("INSERT INTO comments (content, post_id, author_id) VALUES ('event comment', 1, 1)")
            ->execute();

        $events = $this->dispatcher->getEvents(EndQueryEvent::class);
        $this->assertCount(1, $events);
        $this->assertStringContainsString('INSERT INTO comments', $events[0]->sql);
    }

    public function testEventDispatchedOnActiveRecordFind(): void
    {
        $post = Post::model()->findByPk(2);
        $this->assertNotNull($post);

        $sqls = array_map(
            static fn(EndQueryEvent $event): string => $event->sql,
            $this->dispatcher->getEvents(EndQueryEvent::class)
        );

        $this->assertNotEmpty(
            array_filter($sqls, static fn(string $sql): bool => str_contains($sql, 'posts')),
            'No EndQueryEvent dispatched for the ActiveRecord find query'
        );
    }

    public function testEventNotDispatchedWithoutProfiling(): void
    {
        ORMContext::bootstrap($this->containerWithDispatcher(), profile: false);

        $this->connection->createCommand('SELECT id FROM posts')->queryScalar();

        $this->assertSame(0, $this->dispatcher->count(EndQueryEvent::class));
    }

    public function testDispatchWithoutDispatcherDoesNotThrow(): void
    {
        ORMContext::bootstrap(new TestContainer(), profile: true);

        $title = $this->connection->createCommand('SELECT title FROM posts WHERE id = 1')->queryScalar();

        $this->assertSame('post 1', $title);
        $this->assertSame(0, $this->dispatcher->count(EndQueryEvent::class));
    }

    public function testMultipleQueriesDispatchMultipleEvents(): void
    {
        $command = $this->connection->createCommand('SELECT id FROM posts WHERE id = :id');
        $command->queryScalar([':id' => 1]);
        $command->queryScalar([':id' => 2]);
        $command->queryScalar([':id' => 3]);

        $events = $this->dispatcher->getEvents(EndQueryEvent::class);
        $this->assertCount(3, $events);

        $queryIds = array_map(static fn(EndQueryEvent $event): string => $event->queryId, $events);
        $this->assertCount(3, array_unique($queryIds));
    }

    public function testEventIncludesBoundParams(): void
    {
        $this->connection->enableParamLogging = true;

        $command = $this->connection->createCommand('SELECT id FROM posts WHERE id = :id');
        $command->bindValue(':id', 1);
        $command->queryScalar();

        $events = $this->dispatcher->getEvents(EndQueryEvent::class);
        $this->assertCount(1, $events);
        $this->assertArrayHasKey(':id', $events[0]->params);
        $this->assertEquals(1, $events[0]->params[':id']);
    }

    public function testEndQueryEventIsReadonly(): void
    {
        $event = new EndQueryEvent('id', 'SELECT 1', [], 0.0, 'db', $this->connection);

        $this->expectException(\Error::class);
        $event->sql = 'SELECT 2';
    }
}

<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\{PostWithEvents, UserWithEvents};

abstract class AbstractActiveRecordEventsTest extends AbstractDatabaseTest
{
    public static function setUpBeforeClass(): void
    {
        UserWithEvents::model()->refreshMetaData();
        PostWithEvents::model()->refreshMetaData();
    }

    protected function setUp(): void
    {
        parent::setUp();
        ActiveRecord::$db = $this->connection;
        UserWithEvents::model()->counter()->reset();
        PostWithEvents::model()->counter()->reset();
    }

    protected function tearDown(): void
    {
        ActiveRecord::$db = null;
        parent::tearDown();
    }

    // ===== beforeFind / afterFind =====

    public function testBeforeFindCalledOnFindByPk(): void
    {
        $user = UserWithEvents::model()->findByPk(1);
        $this->assertNotNull($user);
        $this->assertSame(1, UserWithEvents::model()->counter()->getCount('beforeFind'));
        $this->assertSame(1, $user->counter()->getCount('afterFind'));
    }

    public function testBeforeFindCalledOnFindByAttributes(): void
    {
        $user = UserWithEvents::model()->findByAttributes(['username' => 'user1']);
        $this->assertNotNull($user);
        $this->assertSame(1, UserWithEvents::model()->counter()->getCount('beforeFind'));
        $this->assertSame(1, $user->counter()->getCount('afterFind'));
    }

    public function testFindAllCallsAfterFindForEachRecord(): void
    {
        $users = UserWithEvents::model()->findAll();
        $this->assertNotEmpty($users);
        foreach ($users as $user) {
            $this->assertSame(1, $user->counter()->getCount('afterFind'));
        }
    }

    public function testFindReturnsNullOnEmptyResult(): void
    {
        $user = UserWithEvents::model()->find('1=0');
        $this->assertNull($user);
        $this->assertSame(1, UserWithEvents::model()->counter()->getCount('beforeFind'));
    }

    public function testEventsCalledOnEagerLoading(): void
    {
        $user = UserWithEvents::model()->with('posts')->findByPk(2);
        $this->assertNotNull($user);
        $this->assertSame(1, UserWithEvents::model()->counter()->getCount('beforeFind'));
        $this->assertSame(1, $user->counter()->getCount('afterFind'));
        $this->assertNotEmpty($user->posts);
        foreach ($user->posts as $post) {
            $this->assertSame(1, $post->counter()->getCount('afterFind'));
        }
    }

    public function testEventsCalledOnLazyLoading(): void
    {
        $user = UserWithEvents::model()->findByPk(2);
        $this->assertNotNull($user);
        $posts = $user->posts;
        $this->assertNotEmpty($posts);
        foreach ($posts as $post) {
            $this->assertSame(1, $post->counter()->getCount('afterFind'));
        }
    }

    // ===== beforeSave / afterSave =====

    public function testBeforeSaveCalledOnInsert(): void
    {
        $user = new UserWithEvents();
        $user->username = 'new_user';
        $user->email = 'new@example.com';
        $user->password = 'password123';
        $this->assertTrue($user->save(false));
        $this->assertSame(1, $user->counter()->getCount('beforeSave'));
        $this->assertSame(1, $user->counter()->getCount('beforeSaveInsert'));
        $this->assertSame(0, $user->counter()->getCount('beforeSaveUpdate'));
        $this->assertSame(1, $user->counter()->getCount('afterSave'));
    }

    public function testBeforeSaveCalledOnUpdate(): void
    {
        $user = UserWithEvents::model()->findByPk(1);
        $this->assertNotNull($user);
        $user->counter()->reset();
        $user->username = 'updated_user';
        $this->assertTrue($user->save(false));
        $this->assertSame(1, $user->counter()->getCount('beforeSave'));
        $this->assertSame(0, $user->counter()->getCount('beforeSaveInsert'));
        $this->assertSame(1, $user->counter()->getCount('beforeSaveUpdate'));
        $this->assertSame(1, $user->counter()->getCount('afterSave'));
    }

    public function testBeforeSaveCanCancelSave(): void
    {
        $user = new UserWithEvents();
        $user->counter()->cancelBeforeSave = true;
        $user->username = 'cancelled_user';
        $user->email = 'cancelled@example.com';
        $user->password = 'password123';
        $this->assertFalse($user->save(false));
        $this->assertSame(1, $user->counter()->getCount('beforeSave'));
        $this->assertSame(0, $user->counter()->getCount('afterSave'));
        $this->assertNull(UserWithEvents::model()->findByAttributes(['username' => 'cancelled_user']));
    }

    public function testBeforeSaveCanModifyAttributes(): void
    {
        $user = new UserWithEvents();
        $user->counter()->attributeModifiers['username'] = 'modified_username';
        $user->username = 'original_username';
        $user->email = 'test@example.com';
        $user->password = 'password123';
        $this->assertTrue($user->save(false));
        $this->assertSame('modified_username', $user->username);
        $this->assertNotNull(UserWithEvents::model()->findByAttributes(['username' => 'modified_username']));
    }

    public function testValidationEventsCalledDuringSave(): void
    {
        $user = new UserWithEvents();
        $user->username = 'test';
        $user->email = 'test@example.com';
        $user->password = 'password123';
        $this->assertTrue($user->save());
        $this->assertSame(1, $user->counter()->getCount('beforeValidate'));
        $this->assertSame(1, $user->counter()->getCount('afterValidate'));
        $this->assertSame(1, $user->counter()->getCount('beforeSave'));
        $this->assertSame(1, $user->counter()->getCount('afterSave'));
    }

    // ===== beforeDelete / afterDelete =====

    public function testBeforeDeleteCalled(): void
    {
        $user = UserWithEvents::model()->findByPk(1);
        $this->assertNotNull($user);
        $this->assertTrue($user->delete());
        $this->assertSame(1, $user->counter()->getCount('beforeDelete'));
        $this->assertSame(1, $user->counter()->getCount('afterDelete'));
    }

    public function testBeforeDeleteCanCancelDelete(): void
    {
        $user = UserWithEvents::model()->findByPk(1);
        $this->assertNotNull($user);
        $user->counter()->cancelBeforeDelete = true;
        $this->assertFalse($user->delete());
        $this->assertSame(1, $user->counter()->getCount('beforeDelete'));
        $this->assertSame(0, $user->counter()->getCount('afterDelete'));
        $this->assertNotNull(UserWithEvents::model()->findByPk(1));
    }

    // ===== beforeFind criteria modification =====

    public function testBeforeFindCanModifyLimit(): void
    {
        $finder = new UserWithEvents();
        $finder->onBeforeFind = fn($e) => $e->sender->getDbCriteria()->mergeWith(['limit' => 2]);
        $this->assertCount(2, $finder->findAll());
    }

    public function testBeforeFindCanModifySelect(): void
    {
        $finder = new UserWithEvents();
        $finder->onBeforeFind = fn($e) => $e->sender->getDbCriteria()->mergeWith(['select' => 'id, username']);
        $user = $finder->findByPk(1);
        $this->assertNotNull($user);
        $this->assertNotNull($user->id);
        $this->assertNotNull($user->username);
    }

    public function testBeforeFindCanModifyCondition(): void
    {
        $finder = new UserWithEvents();
        $finder->onBeforeFind = fn($e) => $e->sender->getDbCriteria()->mergeWith(['condition' => 'id > 2']);
        $users = $finder->findAll();
        $this->assertNotEmpty($users);
        foreach ($users as $user) {
            $this->assertGreaterThan(2, $user->id);
        }
    }

    public function testBeforeFindCanAddEagerLoading(): void
    {
        $finder = new UserWithEvents();
        $finder->onBeforeFind = fn($e) => $e->sender->getDbCriteria()->mergeWith(['with' => 'posts']);
        $user = $finder->findByPk(2);
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRelated('posts'));
    }

    public function testBeforeFindSelectDoesNotAffectStatRelation(): void
    {
        $finder = new UserWithEvents();
        $finder->onBeforeFind = fn($e) => $e->sender->getDbCriteria()->mergeWith(['select' => 'id, username']);
        $user = $finder->findByPk(2);
        $this->assertNotNull($user);
        $postCount = $user->postCount;
        $this->assertIsNumeric($postCount);
        $this->assertGreaterThan(0, (int)$postCount);
    }
}

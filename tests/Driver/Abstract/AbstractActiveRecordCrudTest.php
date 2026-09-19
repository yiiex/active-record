<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Comment;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Order;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Post;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\User;

abstract class AbstractActiveRecordCrudTest extends AbstractDatabaseTest
{
    public static function setUpBeforeClass(): void
    {
        User::model()->refreshMetaData();
        Post::model()->refreshMetaData();
        Comment::model()->refreshMetaData();
        Order::model()->refreshMetaData();
    }

    protected function setUp(): void
    {
        parent::setUp();
        ActiveRecord::$db = $this->connection;
    }

    protected function tearDown(): void
    {
        ActiveRecord::$db = null;
        parent::tearDown();
    }

    // ===== FIND =====

    public function testFind(): void
    {
        $user = User::model()->find();
        $this->assertNotNull($user);
        $this->assertInstanceOf(User::class, $user);
        $this->assertFalse($user->getIsNewRecord());
    }

    public function testFindWithCondition(): void
    {
        $user = User::model()->find('username = :username', [':username' => 'user1']);
        $this->assertNotNull($user);
        $this->assertEquals('user1', $user->username);
    }

    public function testFindReturnsNullWhenNotFound(): void
    {
        $user = User::model()->find('username = :username', [':username' => 'nonexistent']);
        $this->assertNull($user);
    }

    public function testFindAll(): void
    {
        $users = User::model()->findAll();
        $this->assertIsArray($users);
        $this->assertGreaterThan(0, count($users));
        $this->assertInstanceOf(User::class, $users[0]);
    }

    public function testFindAllWithCondition(): void
    {
        $users = User::model()->findAll('username LIKE :pattern', [':pattern' => 'user%']);
        $this->assertIsArray($users);
        $this->assertGreaterThan(0, count($users));
    }

    public function testFindAllReturnsEmptyArrayWhenNotFound(): void
    {
        $users = User::model()->findAll('username = :username', [':username' => 'nonexistent']);
        $this->assertIsArray($users);
        $this->assertEmpty($users);
    }

    public function testFindByPk(): void
    {
        $user = User::model()->findByPk(1);
        $this->assertNotNull($user);
        $this->assertEquals(1, $user->id);
        $this->assertEquals('user1', $user->username);
    }

    public function testFindByPkReturnsNullWhenNotFound(): void
    {
        $user = User::model()->findByPk(99999);
        $this->assertNull($user);
    }

    public function testFindAllByPk(): void
    {
        $users = User::model()->findAllByPk([1, 2, 3]);
        $this->assertIsArray($users);
        $this->assertCount(3, $users);
    }

    public function testFindAllByPkWithSingleValue(): void
    {
        $users = User::model()->findAllByPk(1);
        $this->assertIsArray($users);
        $this->assertCount(1, $users);
        $this->assertEquals(1, $users[0]->id);
    }

    public function testFindByAttributes(): void
    {
        $user = User::model()->findByAttributes(['username' => 'user1']);
        $this->assertNotNull($user);
        $this->assertEquals('user1', $user->username);
    }

    public function testFindByAttributesWithMultipleAttributes(): void
    {
        $user = User::model()->findByAttributes([
            'username' => 'user1',
            'email' => 'user1@example.com',
        ]);
        $this->assertNotNull($user);
        $this->assertEquals('user1', $user->username);
    }

    public function testFindByAttributesReturnsNullWhenNotFound(): void
    {
        $user = User::model()->findByAttributes(['username' => 'nonexistent']);
        $this->assertNull($user);
    }

    public function testFindAllByAttributes(): void
    {
        $users = User::model()->findAllByAttributes(['username' => 'user1']);
        $this->assertIsArray($users);
        $this->assertCount(1, $users);
    }

    // ===== SAVE =====

    public function testSaveInsert(): void
    {
        $user = new User();
        $user->username = 'newuser';
        $user->email = 'newuser@example.com';
        $user->password = 'password123';

        $this->assertTrue($user->getIsNewRecord());

        $result = $user->save();

        $this->assertTrue($result);
        $this->assertFalse($user->getIsNewRecord());
        $this->assertNotNull($user->id);
        $this->assertGreaterThan(0, $user->id);

        // Verify it was actually inserted
        $found = User::model()->findByPk($user->id);
        $this->assertNotNull($found);
        $this->assertEquals('newuser', $found->username);
    }

    public function testSaveUpdate(): void
    {
        $user = User::model()->findByPk(1);
        $this->assertNotNull($user);
        $user->username = 'updated_user';

        $result = $user->save();
        $this->assertTrue($result);

        // Verify it was actually updated
        $found = User::model()->findByPk(1);
        $this->assertNotNull($found);
        $this->assertEquals('updated_user', $found->username);
    }

    public function testSaveWithValidation(): void
    {
        $user = new User();
        $user->username = ''; // Invalid - required
        $user->email = 'test@example.com';

        $result = $user->save(true); // Run validation

        $this->assertFalse($result);
        $this->assertNotEmpty($user->getErrors());
    }

    public function testSaveWithoutValidation(): void
    {
        $user = new User();
        $user->username = 'testuser';
        $user->email = null;           // null violates the NOT NULL constraint
        $user->password = 'password123';

        $this->expectException(DbException::class);
        $user->save(false);
    }

    public function testInsert(): void
    {
        $user = new User();
        $user->username = 'inserted_user';
        $user->email = 'inserted@example.com';
        $user->password = 'password123';

        $result = $user->insert();

        $this->assertTrue($result);
        $this->assertFalse($user->getIsNewRecord());
        $this->assertNotNull($user->id);
    }

    public function testInsertThrowsExceptionOnExistingRecord(): void
    {
        $user = User::model()->findByPk(1);
        $this->assertNotNull($user);

        $this->expectException(DbException::class);
        $user->insert();
    }

    public function testUpdate(): void
    {
        $user = User::model()->findByPk(1);
        $this->assertNotNull($user);

        $user->username = 'updated_via_update';
        $result = $user->update();

        $this->assertTrue($result);

        $found = User::model()->findByPk(1);
        $this->assertEquals('updated_via_update', $found->username);
    }

    public function testUpdateThrowsExceptionOnNewRecord(): void
    {
        $user = new User();
        $user->username = 'test';

        $this->expectException(DbException::class);
        $user->update();
    }

    public function testSaveAttributes(): void
    {
        $user = User::model()->findByPk(1);
        $this->assertNotNull($user);

        $result = $user->saveAttributes(['username' => 'updated_via_saveAttributes']);

        $this->assertTrue($result);

        $found = User::model()->findByPk(1);
        $this->assertEquals('updated_via_saveAttributes', $found->username);
    }

    // ===== MODEL VALIDATION =====

    public function testPostSaveWithValidation(): void
    {
        $post = new Post();
        $post->title = 'validated post';
        $post->create_time = '2024-01-01';
        $post->author_id = 1;

        $this->assertTrue($post->save());
        $this->assertNotNull($post->id);

        $invalid = new Post();
        $invalid->create_time = '2024-01-01';
        $invalid->author_id = 1;

        $this->assertFalse($invalid->save());
        $this->assertTrue($invalid->hasErrors('title'));
    }

    public function testCommentSaveWithValidation(): void
    {
        $comment = new Comment();
        $comment->content = 'validated comment';
        $comment->post_id = 1;
        $comment->author_id = 1;

        $this->assertTrue($comment->save());
        $this->assertNotNull($comment->id);

        $invalid = new Comment();
        $invalid->post_id = 1;
        $invalid->author_id = 1;

        $this->assertFalse($invalid->save());
        $this->assertTrue($invalid->hasErrors('content'));
    }

    // ===== DELETE =====

    public function testDelete(): void
    {
        // Create a user to delete
        $user = new User();
        $user->username = 'to_delete';
        $user->email = 'delete@example.com';
        $user->password = 'password';
        $user->save();

        $id = $user->id;
        $this->assertNotNull($id);

        $result = $user->delete();
        $this->assertTrue($result);

        // Verify it was deleted
        $found = User::model()->findByPk($id);
        $this->assertNull($found);
    }

    public function testDeleteThrowsExceptionOnNewRecord(): void
    {
        $user = new User();

        $this->expectException(DbException::class);
        $user->delete();
    }

    // ===== COUNT & EXISTS =====

    public function testCount(): void
    {
        $count = User::model()->count();
        $this->assertEquals(3, $count);
        $this->assertGreaterThan(0, $count);
    }

    public function testCountWithCondition(): void
    {
        $count = User::model()->count('username LIKE :pattern', [':pattern' => 'user%']);
        $this->assertEquals(3, $count);
        $this->assertGreaterThan(0, $count);
    }

    public function testExists(): void
    {
        $exists = User::model()->exists('username = :username', [':username' => 'user1']);
        $this->assertTrue($exists);
    }

    public function testExistsReturnsFalseWhenNotFound(): void
    {
        $exists = User::model()->exists('username = :username', [':username' => 'nonexistent']);
        $this->assertFalse($exists);
    }

    // ===== REFRESH =====

    public function testRefresh(): void
    {
        $user = User::model()->findByPk(1);
        $this->assertNotNull($user);

        $oldUsername = $user->username;

        // Modify in memory
        $user->username = 'modified_in_memory';

        // Refresh from DB
        $result = $user->refresh();
        $this->assertTrue($result);

        // Should be back to original value
        $this->assertEquals($oldUsername, $user->username);
    }

    // ===== EQUALS =====

    public function testEquals(): void
    {
        $user1 = User::model()->findByPk(1);
        $user2 = User::model()->findByPk(1);

        $this->assertTrue($user1->equals($user2));
    }

    public function testEqualsReturnsFalseForDifferentRecords(): void
    {
        $user1 = User::model()->findByPk(1);
        $user2 = User::model()->findByPk(2);

        $this->assertFalse($user1->equals($user2));
    }

    public function testEqualsReturnsFalseForNewRecord(): void
    {
        $user1 = User::model()->findByPk(1);
        $user2 = new User();

        $this->assertFalse($user1->equals($user2));
    }

    // ===== BULK OPERATIONS =====

    public function testUpdateByPk(): void
    {
        $result = User::model()->updateByPk(
            1,
            ['username' => 'updated_by_pk'],
            'id > 0'
        );

        $this->assertEquals(1, $result);

        $user = User::model()->findByPk(1);
        $this->assertEquals('updated_by_pk', $user->username);
    }

    public function testUpdateByPkArray(): void
    {
        $result = User::model()->updateByPk([1, 2], ['email' => 'bulk@example.com']);

        $this->assertEquals(2, $result);

        $user1 = User::model()->findByPk(1);
        $user2 = User::model()->findByPk(2);
        $this->assertEquals('bulk@example.com', $user1->email);
        $this->assertEquals('bulk@example.com', $user2->email);
    }

    public function testUpdateAll(): void
    {
        $result = User::model()->updateAll(
            ['password' => 'new_password'],
            'username LIKE :pattern',
            [':pattern' => 'user%']
        );

        $this->assertGreaterThan(0, $result);

        $users = User::model()->findAll('username LIKE :pattern', [':pattern' => 'user%']);
        foreach ($users as $user) {
            $this->assertEquals('new_password', $user->password);
        }
    }

    public function testDeleteByPk(): void
    {
        $user = new User();
        $user->username = 'to_delete_pk';
        $user->email = 'delete_pk@example.com';
        $user->password = 'password';
        $user->save();

        $id = $user->id;
        $result = User::model()->deleteByPk($id);

        $this->assertEquals(1, $result);
        $this->assertNull(User::model()->findByPk($id));
    }

    public function testDeleteByPkArray(): void
    {
        $user1 = new User();
        $user1->username = 'delete1';
        $user1->email = 'delete1@example.com';
        $user1->password = 'password';
        $user1->save();

        $user2 = new User();
        $user2->username = 'delete2';
        $user2->email = 'delete2@example.com';
        $user2->password = 'password';
        $user2->save();

        $result = User::model()->deleteByPk([$user1->id, $user2->id]);

        $this->assertEquals(2, $result);
        $this->assertNull(User::model()->findByPk($user1->id));
        $this->assertNull(User::model()->findByPk($user2->id));
    }

    public function testDeleteAll(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $user = new User();
            $user->username = "delete_all_$i";
            $user->email = "delete_all_$i@example.com";
            $user->password = 'password';
            $user->save();
        }

        $result = User::model()->deleteAll(
            'username LIKE :pattern',
            [':pattern' => 'delete_all_%']
        );

        $this->assertEquals(3, $result);

        $count = User::model()->count('username LIKE :pattern', [':pattern' => 'delete_all_%']);
        $this->assertEquals(0, $count);
    }

    public function testDeleteAllByAttributes(): void
    {
        $user1 = new User();
        $user1->username = 'same_email';
        $user1->email = 'same@example.com';
        $user1->password = 'password';
        $user1->save(false);

        $user2 = new User();
        $user2->username = 'same_email2';
        $user2->email = 'same@example.com';
        $user2->password = 'password';
        $user2->save(false);

        $result = User::model()->deleteAllByAttributes(['email' => 'same@example.com']);

        $this->assertEquals(2, $result);

        $count = User::model()->count('email = :email', [':email' => 'same@example.com']);
        $this->assertEquals(0, $count);
    }

    // ===== COUNTERS =====

    public function testUpdateCounters(): void
    {
        $post = Post::model()->findByPk(1);
        $this->assertNotNull($post);
        $this->assertSame(0, (int)$post->view_count);

        // Increment view_count by 1
        $result = Post::model()->updateCounters(['view_count' => 1], 'id = 1');
        $this->assertSame(1, (int)$result);

        $updated = Post::model()->findByPk(1);
        $this->assertNotNull($updated);
        $this->assertSame(1, (int)$updated->view_count);

        // Increment again
        Post::model()->updateCounters(['view_count' => 5], 'id = 1');
        $updated = Post::model()->findByPk(1);
        $this->assertSame(6, (int)$updated->view_count);
    }

    public function testSaveCounters(): void
    {
        $post = Post::model()->findByPk(1);
        $this->assertNotNull($post);
        $this->assertSame(0, (int)$post->view_count);
        $this->assertSame(0, (int)$post->rating);

        $this->assertTrue($post->saveCounters(['view_count' => 1, 'rating' => 2]));

        // in-memory attributes are updated as well
        $this->assertSame(1, (int)$post->view_count);
        $this->assertSame(2, (int)$post->rating);

        $reloaded = Post::model()->findByPk(1);
        $this->assertNotNull($reloaded);
        $this->assertSame(1, (int)$reloaded->view_count);
        $this->assertSame(2, (int)$reloaded->rating);

        // negative delta on a single counter
        $this->assertTrue($post->saveCounters(['view_count' => -3]));
        $this->assertSame(-2, (int)$post->view_count);

        $reloaded = Post::model()->findByPk(1);
        $this->assertSame(-2, (int)$reloaded->view_count);
    }

    // ===== SQL FINDERS =====

    public function testFindBySql(): void
    {
        $post = Post::model()->findBySql('SELECT * FROM posts WHERE id = :id', [':id' => 2]);

        $this->assertInstanceOf(Post::class, $post);
        $this->assertSame(2, (int)$post->id);
    }

    public function testFindBySqlReturnsNullWhenNotFound(): void
    {
        $post = Post::model()->findBySql('SELECT * FROM posts WHERE id = :id', [':id' => 99999]);

        $this->assertNull($post);
    }

    public function testFindAllBySql(): void
    {
        $posts = Post::model()->findAllBySql('SELECT * FROM posts WHERE id > :id', [':id' => 3]);

        $this->assertCount(2, $posts);
        $this->assertInstanceOf(Post::class, $posts[0]);
    }

    public function testCountBySql(): void
    {
        $this->assertEquals(5, Post::model()->countBySql('SELECT COUNT(*) FROM posts'));
    }

    public function testCountByAttributes(): void
    {
        $this->assertEquals(3, Post::model()->countByAttributes(['author_id' => 2]));
        $this->assertEquals(0, Post::model()->countByAttributes(['author_id' => 999]));
    }

    // ===== ATTRIBUTES & METADATA =====

    public function testHasAttribute(): void
    {
        $post = Post::model();

        $this->assertTrue($post->hasAttribute('title'));
        $this->assertTrue($post->hasAttribute('rating'));
        $this->assertFalse($post->hasAttribute('nonexistent'));
    }

    public function testAttributeNames(): void
    {
        $this->assertSame(
            ['id', 'title', 'create_time', 'author_id', 'content', 'view_count', 'rating'],
            Post::model()->attributeNames()
        );
    }

    public function testPrimaryKey(): void
    {
        $post = Post::model()->findByPk(2);
        $this->assertNotNull($post);

        $this->assertEquals(2, $post->getPrimaryKey());
        $this->assertEquals(2, $post->getOldPrimaryKey());
    }

    public function testSetPrimaryKeyKeepsOldPrimaryKey(): void
    {
        $post = Post::model()->findByPk(1);
        $this->assertNotNull($post);

        $post->setPrimaryKey(42);

        $this->assertEquals(42, $post->getPrimaryKey());
        $this->assertEquals(1, $post->getOldPrimaryKey());
    }

    public function testCompositePrimaryKey(): void
    {
        $order = Order::model()->findByPk(['key1' => 1, 'key2' => 2]);
        $this->assertNotNull($order);

        $this->assertEquals(['key1' => 1, 'key2' => 2], $order->getPrimaryKey());
    }

    public function testTableAlias(): void
    {
        $post = new Post();
        $this->assertSame('t', $post->getTableAlias());

        $post->setTableAlias('p');
        $this->assertSame('p', $post->getTableAlias());
        $this->assertSame(
            $this->connection->getSchema()->quoteTableName('p'),
            $post->getTableAlias(true)
        );
    }

    // ===== RELATED RECORDS =====

    public function testAddRelatedRecord(): void
    {
        $post = Post::model()->findByPk(1);
        $author = User::model()->findByPk(1);
        $this->assertNotNull($post);
        $this->assertNotNull($author);
        $this->assertFalse($post->hasRelated('author'));

        $post->addRelatedRecord('author', $author, false);

        $this->assertTrue($post->hasRelated('author'));
        $this->assertSame($author, $post->getRelated('author'));
    }

    public function testAddRelatedRecordWithIntegerIndex(): void
    {
        $post = Post::model()->findByPk(1);
        $comments = Comment::model()->findAll('post_id = 1');
        $this->assertNotNull($post);
        $this->assertCount(3, $comments);

        $post->addRelatedRecord('comments', $comments[0], true);
        $post->addRelatedRecord('comments', $comments[1], true);

        $this->assertTrue($post->hasRelated('comments'));
        $this->assertSame([$comments[0], $comments[1]], $post->getRelated('comments'));
    }

    // ===== SERIALIZATION =====

    public function testSleepReturnsPropertyNames(): void
    {
        $post = Post::model()->findByPk(1);
        $this->assertNotNull($post);

        $properties = $post->__sleep();

        $this->assertIsArray($properties);
        $this->assertNotEmpty($properties);
    }

    public function testSerializeRoundTrip(): void
    {
        $post = Post::model()->findByPk(1);
        $this->assertNotNull($post);

        $restored = unserialize(serialize($post));

        $this->assertInstanceOf(Post::class, $restored);
        $this->assertEquals($post->getPrimaryKey(), $restored->getPrimaryKey());
        $this->assertSame($post->title, $restored->title);
    }
}

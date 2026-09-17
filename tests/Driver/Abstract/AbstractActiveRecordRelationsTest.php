<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Db\Schema\DbCriteria;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Category;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Comment;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Item;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Order;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Post;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Profile;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\User;

abstract class AbstractActiveRecordRelationsTest extends AbstractDatabaseTest
{
    public static function setUpBeforeClass(): void
    {
        User::model()->refreshMetaData();
        Post::model()->refreshMetaData();
        Comment::model()->refreshMetaData();
        Profile::model()->refreshMetaData();
        Category::model()->refreshMetaData();
        Order::model()->refreshMetaData();
        Item::model()->refreshMetaData();
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

    // ===== BELONGS_TO =====

    public function testBelongsToLazy(): void
    {
        $post = Post::model()->findByPk(1);
        $this->assertNotNull($post);

        $author = $post->author;
        $this->assertInstanceOf(User::class, $author);
        $this->assertEquals(1, $author->id);
        $this->assertEquals('user1', $author->username);
    }

    public function testBelongsToEager(): void
    {
        $post = Post::model()->with('author')->findByPk(1);
        $this->assertNotNull($post);
        $this->assertTrue($post->hasRelated('author'));

        $author = $post->author;
        $this->assertInstanceOf(User::class, $author);
        $this->assertEquals('user1', $author->username);
    }

    public function testBelongsToCompositeKey(): void
    {
        $item = Item::model()->findByPk(1);
        $this->assertNotNull($item);

        $order = $item->order;
        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals(1, $order->key1);
        $this->assertEquals(2, $order->key2);
    }

    // ===== HAS_ONE =====

    public function testHasOneLazy(): void
    {
        $user = User::model()->findByPk(1);
        $this->assertNotNull($user);

        $profile = $user->profile;
        $this->assertInstanceOf(Profile::class, $profile);
        $this->assertEquals('first 1', $profile->first_name);
    }

    public function testHasOneEager(): void
    {
        $user = User::model()->with('profile')->findByPk(1);
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRelated('profile'));

        $profile = $user->profile;
        $this->assertInstanceOf(Profile::class, $profile);
    }

    public function testHasOneReturnsNullWhenNotExists(): void
    {
        $user = User::model()->findByPk(3);
        $this->assertNotNull($user);

        $profile = $user->profile;
        $this->assertNull($profile);
    }

    // ===== HAS_MANY =====

    public function testHasManyLazy(): void
    {
        $user = User::model()->findByPk(2);
        $this->assertNotNull($user);

        $posts = $user->posts;
        $this->assertIsArray($posts);
        $this->assertCount(3, $posts);
        $this->assertInstanceOf(Post::class, $posts[0]);
    }

    public function testHasManyEager(): void
    {
        $user = User::model()->with('posts')->findByPk(2);
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRelated('posts'));

        $posts = $user->posts;
        $this->assertCount(3, $posts);
    }

    public function testHasManyEmpty(): void
    {
        $user = new User();
        $user->username = 'empty_user';
        $user->email = 'empty@example.com';
        $user->password = 'password123';
        $user->save(false);

        $posts = $user->posts;
        $this->assertIsArray($posts);
        $this->assertEmpty($posts);
    }

    public function testHasManyCompositeKey(): void
    {
        $order = Order::model()->findByPk(['key1' => 1, 'key2' => 2]);
        $this->assertNotNull($order);

        $items = $order->items;
        $this->assertIsArray($items);
        $this->assertCount(2, $items);
    }

    // ===== MANY_MANY =====

    public function testManyManyLazy(): void
    {
        $post = Post::model()->findByPk(1);
        $this->assertNotNull($post);

        $categories = $post->categories;
        $this->assertIsArray($categories);
        $this->assertCount(3, $categories);
        $this->assertInstanceOf(Category::class, $categories[0]);
    }

    public function testManyManyEager(): void
    {
        $post = Post::model()->with('categories')->findByPk(1);
        $this->assertNotNull($post);
        $this->assertTrue($post->hasRelated('categories'));

        $categories = $post->categories;
        $this->assertCount(3, $categories);
    }

    public function testManyManyReverse(): void
    {
        $category = Category::model()->findByPk(1);
        $this->assertNotNull($category);

        $posts = $category->posts;
        $this->assertIsArray($posts);
        $this->assertCount(3, $posts);
    }

    // ===== SELF-REFERENTIAL =====

    public function testSelfReferentialBelongsTo(): void
    {
        $child = Category::model()->findByPk(4);
        $this->assertNotNull($child);

        $parent = $child->parent;
        $this->assertInstanceOf(Category::class, $parent);
        $this->assertEquals(1, $parent->id);
        $this->assertEquals('cat 1', $parent->name);
    }

    public function testSelfReferentialHasMany(): void
    {
        $parent = Category::model()->findByPk(1);
        $this->assertNotNull($parent);

        $children = $parent->children;
        $this->assertIsArray($children);
        $this->assertCount(2, $children);
    }

    public function testSelfReferentialNull(): void
    {
        $root = Category::model()->findByPk(1);
        $this->assertNotNull($root);

        $parent = $root->parent;
        $this->assertNull($parent);
    }

    // ===== NESTED RELATIONS =====

    public function testNestedRelationsEager(): void
    {
        $user = User::model()->with('posts.comments')->findByPk(2);
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRelated('posts'));

        $posts = $user->posts;
        $this->assertCount(3, $posts);

        $totalComments = 0;
        foreach ($posts as $post) {
            $this->assertTrue($post->hasRelated('comments'));
            $totalComments += count($post->comments);
        }

        $this->assertGreaterThan(0, $totalComments);
    }

    public function testNestedRelationsLazy(): void
    {
        $user = User::model()->findByPk(2);
        $this->assertNotNull($user);

        $posts = $user->posts;
        $this->assertCount(3, $posts);

        $firstPost = $posts[0];
        $comments = $firstPost->comments;
        $this->assertIsArray($comments);
        $this->assertGreaterThan(0, count($comments));
    }

    // ===== RELATIONS WITH CONDITIONS =====

    public function testRelationWithCondition(): void
    {
        $user = User::model()->findByPk(2);
        $this->assertNotNull($user);

        $posts = $user->posts(['condition' => 'id > 2']);
        $this->assertIsArray($posts);
        $this->assertCount(2, $posts);
    }

    public function testRelationWithOrder(): void
    {
        $user = User::model()->findByPk(2);
        $this->assertNotNull($user);

        $posts = $user->posts(['order' => 'id DESC']);
        $this->assertIsArray($posts);
        $this->assertCount(3, $posts);
        $this->assertGreaterThan($posts[1]->id, $posts[0]->id);
    }

    public function testRelationWithLimit(): void
    {
        $user = User::model()->findByPk(2);
        $this->assertNotNull($user);

        $posts = $user->posts(['limit' => 2]);
        $this->assertIsArray($posts);
        $this->assertCount(2, $posts);
    }

    // ===== EAGER LOADING WITH DBCRITERIA =====

    public function testEagerLoadingWithCriteria(): void
    {
        $criteria = new DbCriteria();
        $criteria->with = ['author', 'comments'];

        $posts = Post::model()->findAll($criteria);

        $this->assertNotEmpty($posts);
        foreach ($posts as $post) {
            $this->assertTrue($post->hasRelated('author'));
            $this->assertTrue($post->hasRelated('comments'));
            $this->assertInstanceOf(User::class, $post->author);
        }
    }

    public function testEagerLoadingWithCriteriaAndCondition(): void
    {
        $criteria = new DbCriteria();
        $criteria->with = ['author'];
        $criteria->condition = 'author_id = :author_id';
        $criteria->params = [':author_id' => 2];

        $posts = Post::model()->findAll($criteria);

        $this->assertNotEmpty($posts);
        foreach ($posts as $post) {
            $this->assertEquals(2, $post->author_id);
            $this->assertTrue($post->hasRelated('author'));
            $this->assertEquals('user2', $post->author->username);
        }
    }

    public function testEagerLoadingWithCriteriaArray(): void
    {
        $posts = Post::model()->findAll([
            'with' => ['categories', 'comments'],
            'order' => 't.id DESC',
            'limit' => 3,
        ]);

        $this->assertCount(3, $posts);
        foreach ($posts as $post) {
            $this->assertTrue($post->hasRelated('categories'));
            $this->assertTrue($post->hasRelated('comments'));
        }
    }

    public function testNestedEagerLoadingWithCriteria(): void
    {
        $criteria = new DbCriteria([
            'with' => [
                'posts.comments',
                'posts.categories',
            ],
        ]);

        $users = User::model()->findAll($criteria);

        $this->assertNotEmpty($users);
        foreach ($users as $user) {
            $this->assertTrue($user->hasRelated('posts'));
            foreach ($user->posts as $post) {
                $this->assertTrue($post->hasRelated('comments'));
                $this->assertTrue($post->hasRelated('categories'));
            }
        }
    }

    public function testCriteriaWithSelectFalse(): void
    {
        $criteria = new DbCriteria([
            'with' => [
                'posts' => [
                    'select' => false,
                    'joinType' => 'INNER JOIN',
                    'condition' => 'posts.id > 2',
                ],
            ],
        ]);
        $users = User::model()->findAll($criteria);

        $this->assertNotEmpty($users);
        foreach ($users as $user) {
            $this->assertFalse($user->hasRelated('posts'));
        }
    }

    public function testDynamicCriteriaOnLazyLoad(): void
    {
        $user = User::model()->findByPk(2);
        $this->assertNotNull($user);

        $posts = $user->posts([
            'condition' => 'id > 3',
            'order' => 'id DESC',
        ]);

        $this->assertIsArray($posts);
        $this->assertCount(1, $posts);
        $this->assertEquals(4, $posts[0]->id);
    }

    // ===== THROUGH RELATIONS (BELONGS_TO) =====

    public function testThroughBelongsToLazy(): void
    {
        $comments = Comment::model()->findAll();
        $this->assertNotEmpty($comments);

        foreach ($comments as $comment) {
            $this->assertNotNull($comment->postAuthor);
            $this->assertInstanceOf(User::class, $comment->postAuthor);

            $this->assertTrue($comment->postAuthor->equals($comment->post->author));
        }
    }

    public function testThroughBelongsToEager(): void
    {
        $comments = Comment::model()->with('postAuthor')->findAll();
        $this->assertNotEmpty($comments);

        foreach ($comments as $comment) {
            $this->assertTrue($comment->hasRelated('postAuthor'));
            $this->assertNotNull($comment->postAuthor);
            $this->assertInstanceOf(User::class, $comment->postAuthor);
        }
    }

    public function testThroughSpecificComment(): void
    {
        $comment = Comment::model()->findByPk(1);
        $this->assertNotNull($comment);

        $postAuthor = $comment->postAuthor;
        $this->assertNotNull($postAuthor);
        $this->assertEquals(1, $postAuthor->id);
        $this->assertEquals('user1', $postAuthor->username);

        $this->assertTrue($postAuthor->equals($comment->post->author));
    }

    // ===== STAT RELATIONS =====

    public function testStatRelation(): void
    {
        $user = User::model()->findByPk(2);
        $this->assertNotNull($user);

        $postCount = $user->postCount;
        $this->assertEquals(3, $postCount);
    }

    public function testStatRelationNonZero(): void
    {
        $user = User::model()->findByPk(2);
        $this->assertNotNull($user);

        $commentCount = $user->commentCount;
        $this->assertGreaterThan(0, (int)$commentCount);
    }

    public function testStatRelationZero(): void
    {
        $user = new User();
        $user->username = 'empty_user_stat';
        $user->email = 'empty_stat@example.com';
        $user->password = 'password123';
        $user->save(false);

        $postCount = $user->postCount;
        $this->assertEquals(0, $postCount);
    }
}

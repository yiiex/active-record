<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\ConditionBuilder;
use Yii1x\ActiveRecord\Db\Schema\DbCriteria;
use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Category;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Comment;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Item;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Order;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Post;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\PostWithScopes;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\User;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\UserWithScopedPosts;

abstract class AbstractQueryBuilderTest extends AbstractDatabaseTest
{
    public static function setUpBeforeClass(): void
    {
        foreach ([User::class, Post::class, Comment::class, Category::class, Order::class, Item::class, PostWithScopes::class, UserWithScopedPosts::class] as $class) {
            $class::model()->refreshMetaData();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        ActiveRecord::$db = $this->connection;

        foreach ([User::class, Post::class, Comment::class, Category::class, Order::class, Item::class, UserWithScopedPosts::class] as $class) {
            $class::model()->setTableAlias('t');
            $class::model()->resetScope(false);
        }
    }

    protected function tearDown(): void
    {
        ActiveRecord::$db = null;
        parent::tearDown();
    }

    /**
     * @param ActiveRecord[] $records
     * @return int[]
     */
    private function ids(array $records): array
    {
        return array_map(static fn(ActiveRecord $record): int => (int)$record->id, $records);
    }

    // ===== Basic query building =====

    public function testSelectAndWhere(): void
    {
        $post = Post::queryBuilder()->select(['id'])->where('id', 2)->find();

        $this->assertNotNull($post);
        $this->assertSame(2, (int)$post->id);
    }

    public function testDistinct(): void
    {
        $posts = Post::queryBuilder()->select(['author_id'])->distinct()->findAll();

        $this->assertCount(3, $posts);
    }

    public function testOrderByLimitOffset(): void
    {
        $posts = Post::queryBuilder()->orderBy('id DESC')->limit(2)->offset(1)->findAll();

        $this->assertSame([4, 3], $this->ids($posts));
    }

    public function testGroupByHaving(): void
    {
        $posts = Post::queryBuilder()
            ->select('author_id, COUNT(*) AS cnt')
            ->groupBy('author_id')
            ->having('COUNT(*) >= 3')
            ->findAll();

        $this->assertCount(1, $posts);
        $this->assertSame(2, (int)$posts[0]->author_id);
    }

    public function testIndexBy(): void
    {
        $posts = Post::queryBuilder()->orderBy('id')->indexBy('id')->findAll();

        $this->assertSame([1, 2, 3, 4, 5], array_keys($posts));
    }

    public function testWhereOperator(): void
    {
        $this->assertSame([4, 5], $this->ids(Post::queryBuilder()->where('id', '>=', 4)->findAll()));
    }

    public function testWhereClosureWithOr(): void
    {
        $posts = Post::queryBuilder()
            ->where(fn(ConditionBuilder $cb) => $cb->where('id', 1)->where('id', 2, operator: 'OR'))
            ->findAll();

        $this->assertSame([1, 2], $this->ids($posts));
    }

    public function testWhereInAndNotIn(): void
    {
        $this->assertCount(2, Post::queryBuilder()->whereIn('id', [1, 2])->findAll());
        $this->assertCount(0, Post::queryBuilder()->whereNotIn('id', [1, 2, 3, 4, 5])->findAll());
    }

    public function testWhereInEmpty(): void
    {
        $this->assertCount(0, Post::queryBuilder()->whereIn('id', [])->findAll());
    }

    public function testWhereNullAndNotNull(): void
    {
        $this->assertCount(0, Post::queryBuilder()->whereNull('content')->findAll());
        $this->assertCount(5, Post::queryBuilder()->whereNotNull('content')->findAll());
    }

    public function testWhereBetween(): void
    {
        $this->assertSame([2, 3, 4], $this->ids(Post::queryBuilder()->whereBetween('id', 2, 4)->findAll()));
    }

    public function testWhereRawWithParams(): void
    {
        $this->assertSame([4, 5], $this->ids(Post::queryBuilder()->whereRaw('id > :min', [':min' => 3])->findAll()));
    }

    public function testLike(): void
    {
        $this->assertSame([1], $this->ids(Post::queryBuilder()->like('title', 'post 1')->findAll()));
    }

    public function testWhen(): void
    {
        $this->assertCount(1, Post::queryBuilder()->when(true, fn($qb) => $qb->where('id', 1))->findAll());

        $this->assertCount(
            2,
            Post::queryBuilder()
                ->when(false, fn($qb) => $qb->where('id', 1), fn($qb) => $qb->whereIn('id', [1, 2]))
                ->findAll()
        );
    }

    public function testForkIsIndependent(): void
    {
        $base = Post::queryBuilder()->where('author_id', 2);

        $this->assertCount(2, $base->fork()->where('id', '<', 4)->findAll());
        $this->assertCount(3, $base->fork()->findAll());
        $this->assertCount(3, $base->findAll());
    }

    public function testExecutionDoesNotMutateCriteria(): void
    {
        $qb = Post::queryBuilder()->where('id', 1);
        $condition = $qb->criteria->condition;

        $qb->count();
        $qb->find();
        $qb->findAll();

        $this->assertSame($condition, $qb->criteria->condition);
    }

    public function testCloneKeepsCriteriaIndependent(): void
    {
        $qb = Post::queryBuilder()->where('id', 2);
        $clone = clone $qb;

        $this->assertNotSame($qb->criteria, $clone->criteria);

        $clone->where('id', 3); // (id=2) AND (id=3)

        $this->assertSame(0, $clone->count());
        $this->assertSame(1, $qb->count());
    }

    // ===== whereRelation =====

    public function testWhereRelationHasMany(): void
    {
        $this->assertSame([1, 2, 3, 5], $this->ids(Post::queryBuilder()->whereRelation('comments')->findAll()));
    }

    public function testWhereRelationHasManyWithCallback(): void
    {
        $posts = Post::queryBuilder()
            ->whereRelation('comments', fn(ConditionBuilder $cb) => $cb->where('id', '>', 5))
            ->findAll();

        $this->assertSame([3, 5], $this->ids($posts));
    }

    public function testWhereRelationBelongsTo(): void
    {
        $posts = Post::queryBuilder()
            ->whereRelation('author', fn(ConditionBuilder $cb) => $cb->where('username', 'user1'))
            ->findAll();

        $this->assertSame([1], $this->ids($posts));
    }

    public function testWhereRelationHasOne(): void
    {
        $this->assertSame([1, 2], $this->ids(User::queryBuilder()->whereRelation('profile')->findAll()));
    }

    public function testWhereRelationManyMany(): void
    {
        $this->assertSame([1, 2, 3], $this->ids(Post::queryBuilder()->whereRelation('categories')->findAll()));
    }

    public function testWhereRelationManyManyWithCallback(): void
    {
        $posts = Post::queryBuilder()
            ->whereRelation('categories', fn(ConditionBuilder $cb) => $cb->where('id', 1))
            ->findAll();

        $this->assertSame([1, 2, 3], $this->ids($posts));
    }

    public function testWhereRelationNested(): void
    {
        $users = User::queryBuilder()
            ->whereRelation('posts.comments', fn(ConditionBuilder $cb) => $cb->where('id', '>', 5))
            ->findAll();

        $this->assertSame([2, 3], $this->ids($users));
    }

    public function testWhereRelationThrough(): void
    {
        $comments = Comment::queryBuilder()
            ->whereRelation('postAuthor', fn(ConditionBuilder $cb) => $cb->where('username', 'user1'))
            ->findAll();

        $this->assertSame([1, 2, 3], $this->ids($comments));
    }

    public function testWhereRelationCamelCaseAlias(): void
    {
        $users = UserWithScopedPosts::queryBuilder()->whereRelation('recentPosts')->findAll();

        $this->assertSame([1, 2, 3], $this->ids($users));
    }

    public function testWhereRelationWithCustomAlias(): void
    {
        $this->assertCount(4, Post::queryBuilder()->whereRelation('comments', null, 'AND', 'c')->findAll());
    }

    public function testWhereRelationWithOrOperator(): void
    {
        $posts = Post::queryBuilder()
            ->where('id', 1)
            ->whereRelation('comments', null, 'OR')
            ->findAll();

        $this->assertSame([1, 2, 3, 5], $this->ids($posts));
    }

    public function testWhereRelationHonorsRelationCondition(): void
    {
        // old_comments condition: comments.id > 5 -> posts 3 (ids 6-9) and 5 (id 10)
        $this->assertSame([3, 5], $this->ids(Post::queryBuilder()->whereRelation('old_comments')->findAll()));
    }

    public function testWhereRelationDoesNotLeakAlias(): void
    {
        Comment::model()->setTableAlias('t');

        Post::queryBuilder()->whereRelation('comments')->findAll();

        $this->assertSame('t', Comment::model()->getTableAlias(false, false));
        $this->assertNotNull(Comment::model()->findByPk(1));
    }

    public function testWhereRelationUnknownRelationThrows(): void
    {
        $this->expectException(DbException::class);

        Post::queryBuilder()->whereRelation('does_not_exist')->findAll();
    }

    public function testWhereRelationManyManyThroughThrows(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('does not support through');

        Post::queryBuilder()->whereRelation('categoriesThrough')->findAll();
    }

    public function testWhereRelationCompositeKeyThrows(): void
    {
        $this->expectException(DbException::class);

        Order::queryBuilder()->whereRelation('items')->findAll();
    }

    // ===== whereHas (ConditionBuilder) =====

    public function testWhereHas(): void
    {
        $builder = new ConditionBuilder(Post::model(), new DbCriteria(['alias' => 't']));
        $builder->whereHas('comments', fn(ConditionBuilder $cb) => $cb->where('id', '>', 5));

        $this->assertStringContainsString('EXISTS', $builder->criteria->condition);
        $this->assertNotEmpty($builder->criteria->params);
    }

    public function testWhereHasUnknownTableThrows(): void
    {
        $builder = new ConditionBuilder(Post::model(), new DbCriteria(['alias' => 't']));

        $this->expectException(DbException::class);
        $builder->whereHas('nonexistent_table');
    }

    // ===== Joins =====

    public function testJoinBuildsSql(): void
    {
        $inner = Post::queryBuilder()->innerJoin('users', 'users.id = t.author_id');
        $this->assertStringContainsString('INNER JOIN', $inner->criteria->join);

        $left = Post::queryBuilder()->leftJoin('users', 'users.id = t.author_id');
        $this->assertStringContainsString('LEFT JOIN', $left->criteria->join);

        $right = Post::queryBuilder()->rightJoin('users', 'users.id = t.author_id');
        $this->assertStringContainsString('RIGHT JOIN', $right->criteria->join);
    }

    public function testInnerJoinFilters(): void
    {
        $posts = Post::queryBuilder()
            ->innerJoin('users', 'users.id = t.author_id')
            ->where('users.username', 'user1')
            ->findAll();

        $this->assertSame([1], $this->ids($posts));
    }

    // ===== Eager loading & execution =====

    public function testWithEagerLoadsRelation(): void
    {
        $posts = Post::queryBuilder()->with('author')->findAll();

        $this->assertCount(5, $posts);
        foreach ($posts as $post) {
            $this->assertTrue($post->hasRelated('author'));
        }
    }

    public function testTogetherSetsFlag(): void
    {
        $qb = Post::queryBuilder()->with('comments')->together();

        $this->assertTrue($qb->criteria->together);
    }

    public function testCountExistsFindFindAll(): void
    {
        $this->assertSame(5, Post::queryBuilder()->count());
        $this->assertTrue(Post::queryBuilder()->where('id', 1)->exists());
        $this->assertFalse(Post::queryBuilder()->where('id', 999)->exists());
        $this->assertSame(2, (int)Post::queryBuilder()->where('id', 2)->find()->id);
        $this->assertCount(3, Post::queryBuilder()->where('author_id', 2)->findAll());
    }

    public function testDeleteAll(): void
    {
        $this->assertSame(1, Post::queryBuilder()->where('id', 5)->deleteAll());
        $this->assertNull(Post::model()->findByPk(5));
    }

    // ===== ConditionBuilder =====

    public function testConditionBuilderBuildsCriteria(): void
    {
        $builder = new ConditionBuilder(Post::model(), new DbCriteria(['alias' => 't']));
        $builder->whereIn('id', [1, 2])
            ->whereNull('content')
            ->whereBetween('rating', 1, 5)
            ->whereRaw('author_id > :a', [':a' => 1]);

        $this->assertStringContainsString('IN', $builder->criteria->condition);
        $this->assertStringContainsString('IS NULL', $builder->criteria->condition);
        $this->assertStringContainsString('BETWEEN', $builder->criteria->condition);
        $this->assertStringContainsString('author_id > :a', $builder->criteria->condition);
        $this->assertSame(1, $builder->criteria->params[':a']);
    }

    public function testConditionBuilderScopesDeferred(): void
    {
        $builder = new ConditionBuilder(PostWithScopes::model(), new DbCriteria(['alias' => 't']));
        $builder->scopes('post23');

        $this->assertSame(['post23'], $builder->criteria->scopes);
    }

    public function testConditionBuilderApplyScopesImmediate(): void
    {
        $builder = new ConditionBuilder(PostWithScopes::model(), new DbCriteria(['alias' => 't']));
        $builder->applyScopes('post23');

        $this->assertStringContainsString('id>=', $builder->criteria->condition);
        $this->assertStringContainsString('id<=', $builder->criteria->condition);
        $this->assertNotEmpty($builder->criteria->params);
    }
}

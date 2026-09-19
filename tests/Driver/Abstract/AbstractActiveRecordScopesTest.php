<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Db\Schema\DbCriteria;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Category;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\CategoryWithScopedPosts;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Comment;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Post;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\PostWithDefaultScope;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\PostWithDefaultScopeAlias;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\PostWithScopes;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\Profile;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\User;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\UserWithScopedPosts;

/**
 * Covers named scopes, default scope, parameterized scopes and scopes on relations.
 */
abstract class AbstractActiveRecordScopesTest extends AbstractDatabaseTest
{
    public static function setUpBeforeClass(): void
    {
        User::model()->refreshMetaData();
        Post::model()->refreshMetaData();
        Comment::model()->refreshMetaData();
        Profile::model()->refreshMetaData();
        Category::model()->refreshMetaData();
        PostWithScopes::model()->refreshMetaData();
        PostWithDefaultScope::model()->refreshMetaData();
        PostWithDefaultScopeAlias::model()->refreshMetaData();
        UserWithScopedPosts::model()->refreshMetaData();
        CategoryWithScopedPosts::model()->refreshMetaData();
    }

    protected function setUp(): void
    {
        parent::setUp();
        ActiveRecord::$db = $this->connection;
    }

    protected function tearDown(): void
    {
        PostWithScopes::model()->resetScope(false);
        PostWithDefaultScope::model()->resetScope(false);
        PostWithDefaultScopeAlias::model()->resetScope(false);
        UserWithScopedPosts::model()->resetScope(false);
        CategoryWithScopedPosts::model()->resetScope(false);
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

    /**
     * @param ActiveRecord[] $records
     * @return int[]
     */
    private function sortedIds(array $records): array
    {
        $ids = $this->ids($records);
        sort($ids);

        return $ids;
    }

    // ===== Named scopes declared in scopes() =====

    public function testNamedScopeViaMagicCall(): void
    {
        $this->assertSame([2, 3], $this->ids(PostWithScopes::model()->post23()->findAll()));
    }

    public function testNamedScopeViaFindAllOption(): void
    {
        $this->assertSame([2, 3], $this->ids(PostWithScopes::model()->findAll(['scopes' => 'post23'])));
    }

    public function testNamedScopeArrayOption(): void
    {
        $this->assertSame([2, 3], $this->ids(PostWithScopes::model()->findAll(['scopes' => ['post23']])));
    }

    public function testNamedScopeChaining(): void
    {
        $this->assertSame([3, 2], $this->ids(PostWithScopes::model()->post23()->recent()->findAll()));
    }

    public function testNamedScopeWithCondition(): void
    {
        $this->assertSame([3], $this->ids(PostWithScopes::model()->post23()->findAll('id=3')));
    }

    public function testNamedScopeWithConditionAndScopeOption(): void
    {
        $this->assertSame(
            [3],
            $this->ids(PostWithScopes::model()->findAll(['condition' => 'id=3', 'scopes' => 'post23']))
        );
    }

    public function testNamedScopeOrder(): void
    {
        $this->assertSame([5, 4, 3, 2, 1], $this->ids(PostWithScopes::model()->recent()->findAll()));
    }

    public function testNamedScopeWithLimit(): void
    {
        $this->assertSame([5, 4], $this->ids(PostWithScopes::model()->recent()->findAll(['limit' => 2])));
    }

    public function testNamedScopeWithRelation(): void
    {
        $posts = PostWithScopes::model()->post23()->with('author')->findAll();

        $this->assertSame([2, 3], $this->ids($posts));
        foreach ($posts as $post) {
            $this->assertTrue($post->hasRelated('author'));
        }
    }

    public function testNamedScopeCountAndExists(): void
    {
        $this->assertSame(2, (int)PostWithScopes::model()->post23()->count());
        $this->assertTrue(PostWithScopes::model()->post23()->exists('id=3'));
        $this->assertFalse(PostWithScopes::model()->post23()->exists('id=1'));
    }

    // ===== Method based and parameterized scopes =====

    public function testMethodScope(): void
    {
        $this->assertSame([2, 3, 4], $this->sortedIds(PostWithScopes::model()->byAuthor(2)->findAll()));
    }

    public function testMethodScopeParameterizedArray(): void
    {
        $this->assertSame(
            [2, 3, 4],
            $this->sortedIds(PostWithScopes::model()->findAll(['scopes' => ['byAuthor' => 2]]))
        );
    }

    public function testMethodScopeParameterizedNestedArray(): void
    {
        $this->assertSame(
            [2, 3, 4],
            $this->sortedIds(PostWithScopes::model()->findAll(['scopes' => [['byAuthor' => 2]]]))
        );
    }

    public function testMethodScopeParameterizedArrayParam(): void
    {
        $this->assertSame(
            [2, 3, 4],
            $this->sortedIds(PostWithScopes::model()->findAll(['scopes' => [['byAuthor' => [2]]]]))
        );
    }

    public function testMethodScopeWithBoundParam(): void
    {
        $this->assertSame([4, 5], $this->sortedIds(PostWithScopes::model()->after(3)->findAll()));
    }

    public function testScopeOptionOnCriteriaObject(): void
    {
        $criteria = new DbCriteria();
        $criteria->scopes = 'post23';

        $this->assertSame([2, 3], $this->ids(PostWithScopes::model()->findAll($criteria)));
    }

    // ===== DbCriteria scopes merging =====

    public function testDbCriteriaMergesScopesFromIntKeys(): void
    {
        $criteria = new DbCriteria();
        $criteria->scopes = ['post23', 'recent'];

        $merged = new DbCriteria();
        $merged->mergeWith($criteria);

        $this->assertSame(['post23', 'recent'], $merged->toArray()['scopes']);
    }

    public function testDbCriteriaMergesScopesFromStringKeys(): void
    {
        $criteria = new DbCriteria();
        $criteria->scopes = ['byAuthor' => 3];

        $merged = new DbCriteria();
        $merged->mergeWith($criteria);

        $this->assertSame(['byAuthor' => 3], $merged->toArray()['scopes']);
    }

    public function testDbCriteriaMergesDuplicateScopes(): void
    {
        $first = new DbCriteria();
        $first->scopes = ['byAuthor' => 1];

        $second = new DbCriteria();
        $second->scopes = ['byAuthor' => 2];

        $first->mergeWith($second);

        $this->assertSame([['byAuthor' => 1], ['byAuthor' => 2]], $first->toArray()['scopes']);
    }

    // ===== Default scope =====

    public function testDefaultScopeAppliedOnFindAll(): void
    {
        $this->assertSame([2, 3, 4], $this->sortedIds(PostWithDefaultScope::model()->findAll()));
    }

    public function testDefaultScopeAppliedOnFindByPk(): void
    {
        $this->assertNotNull(PostWithDefaultScope::model()->findByPk(2));
        $this->assertNull(PostWithDefaultScope::model()->findByPk(1));
    }

    public function testDefaultScopeAppliedOnCount(): void
    {
        $this->assertSame(3, (int)PostWithDefaultScope::model()->count());
    }

    public function testDefaultScopeAppliedOnExists(): void
    {
        $this->assertFalse(PostWithDefaultScope::model()->exists('id=1'));
        $this->assertTrue(PostWithDefaultScope::model()->exists('id=2'));
    }

    public function testDefaultScopeCombinedWithNamedScope(): void
    {
        $this->assertSame([4, 3, 2], $this->ids(PostWithDefaultScope::model()->desc()->findAll()));
    }

    public function testDefaultScopeNotAppliedOnUpdate(): void
    {
        $affected = PostWithDefaultScope::model()->updateAll(['view_count' => 77], 'id=1');
        $this->assertSame(1, $affected);

        $post = PostWithDefaultScope::model()->resetScope()->findByPk(1);
        $this->assertNotNull($post);
        $this->assertSame(77, (int)$post->view_count);
    }

    public function testDefaultScopeNotAppliedOnDelete(): void
    {
        $deleted = PostWithDefaultScope::model()->deleteAll('id=1');
        $this->assertSame(1, $deleted);

        $this->assertNull(PostWithDefaultScope::model()->resetScope()->findByPk(1));
    }

    public function testDefaultScopeNotAppliedOnInsert(): void
    {
        $post = new PostWithDefaultScope();
        $post->title = 'scoped insert';
        $post->create_time = '2024-01-01';
        $post->author_id = 1;

        $this->assertTrue($post->save(false));

        $found = PostWithDefaultScope::model()->resetScope()->findByPk($post->id);
        $this->assertNotNull($found);
        $this->assertSame(1, (int)$found->author_id);
    }

    public function testDefaultScopeWithAliasDoesNotRecurse(): void
    {
        $this->assertSame([2, 3, 4], $this->sortedIds(PostWithDefaultScopeAlias::model()->findAll()));
    }

    public function testResetScopeRemovesDefaultScope(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], $this->sortedIds(PostWithDefaultScope::model()->resetScope()->findAll()));
    }

    public function testResetScopeKeepsDefaultScopeWhenResetDefaultFalse(): void
    {
        $this->assertSame([2, 3, 4], $this->sortedIds(PostWithDefaultScope::model()->resetScope(false)->findAll()));
    }

    public function testSetDbCriteriaOverridesDefaultScope(): void
    {
        $model = new PostWithDefaultScope();
        $model->setDbCriteria(new DbCriteria());

        $this->assertSame([1, 2, 3, 4, 5], $this->sortedIds($model->findAll()));
    }

    // ===== Scopes on relations =====

    public function testRelationDeclaredScopeLazy(): void
    {
        $user = UserWithScopedPosts::model()->findByPk(2);
        $this->assertNotNull($user);
        $this->assertSame([2, 3], $this->sortedIds($user->posts));
    }

    public function testRelationDeclaredOrderScopeLazy(): void
    {
        $user = UserWithScopedPosts::model()->findByPk(2);
        $this->assertNotNull($user);
        $this->assertSame([4, 3, 2], $this->ids($user->recentPosts));
    }

    public function testRelationDeclaredScopeEager(): void
    {
        $user = UserWithScopedPosts::model()->with('posts')->findByPk(2);
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRelated('posts'));
        $this->assertSame([2, 3], $this->sortedIds($user->posts));
    }

    public function testRelationColonScope(): void
    {
        $user = UserWithScopedPosts::model()->with('allPosts:post23')->findByPk(2);
        $this->assertNotNull($user);
        $this->assertSame([2, 3], $this->sortedIds($user->allPosts));
    }

    public function testRelationScopesOption(): void
    {
        $user = UserWithScopedPosts::model()
            ->with(['allPosts' => ['scopes' => 'post23']])
            ->findByPk(2);
        $this->assertNotNull($user);
        $this->assertSame([2, 3], $this->sortedIds($user->allPosts));
    }

    public function testRelationParameterizedScope(): void
    {
        $user = UserWithScopedPosts::model()
            ->with(['allPosts' => ['scopes' => ['after' => 3]]])
            ->findByPk(2);
        $this->assertNotNull($user);
        $this->assertSame([4], $this->sortedIds($user->allPosts));
    }

    public function testRelationParameterizedScopeNested(): void
    {
        $user = UserWithScopedPosts::model()
            ->with(['allPosts' => ['scopes' => [['after' => 3]]]])
            ->findByPk(2);
        $this->assertNotNull($user);
        $this->assertSame([4], $this->sortedIds($user->allPosts));
    }

    public function testStatRelationWithScopeLazy(): void
    {
        $user = UserWithScopedPosts::model()->findByPk(2);
        $this->assertNotNull($user);
        $this->assertSame(2, (int)$user->postCount);
    }

    public function testStatRelationWithScopeEager(): void
    {
        $users = UserWithScopedPosts::model()->with('postCount')->findAll();

        $counts = [];
        foreach ($users as $user) {
            $counts[(int)$user->id] = (int)$user->postCount;
        }

        $this->assertSame(0, $counts[1]);
        $this->assertSame(2, $counts[2]);
        $this->assertSame(0, $counts[3]);
    }

    public function testManyManyStatRelationWithScope(): void
    {
        $first = CategoryWithScopedPosts::model()->findByPk(1);
        $second = CategoryWithScopedPosts::model()->findByPk(4);
        $third = CategoryWithScopedPosts::model()->findByPk(2);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotNull($third);

        $this->assertSame(2, (int)$first->postCount);
        $this->assertSame(1, (int)$second->postCount);
        $this->assertSame(0, (int)$third->postCount);
    }

    // ===== Errors =====

    public function testUnknownScopeThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        PostWithScopes::model()->findAll(['scopes' => 'doesNotExist']);
    }
}

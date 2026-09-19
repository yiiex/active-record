<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionProperty;
use Yii1x\ActiveRecord\ActiveRecord;
use Yii1x\ActiveRecord\Db\DbConnection;
use Yii1x\ActiveRecord\Exceptions\DbException;
use Yii1x\ActiveRecord\ORMContext;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\AttrAlpha;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\AttrBeta;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\AttrBoth;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\AttrPlain;
use Yii1x\ActiveRecord\Tests\Infrastructure\Models\AttrTable;
use Yii1x\ActiveRecord\Tests\Infrastructure\TestContainer;

/**
 * Covers the #[Table] and #[Database] class attributes and the connection
 * resolution driven by #[Database] (see issue #1: per-model connections).
 *
 * No real database is required: DbConnection is created with autoConnect=false,
 * and only the resolution logic is exercised.
 */
class ActiveRecordAttributesTest extends TestCase
{
    private DbConnection $connectionAlpha;
    private DbConnection $connectionBeta;
    private DbConnection $connectionDefault;

    protected function setUp(): void
    {
        $this->resetModelSingletons();

        ActiveRecord::$db = null;

        $this->connectionAlpha = new DbConnection('sqlite::memory:', '', '', 'alpha', autoConnect: false);
        $this->connectionBeta = new DbConnection('sqlite::memory:', '', '', 'beta', autoConnect: false);
        $this->connectionDefault = new DbConnection('sqlite::memory:', '', '', 'default', autoConnect: false);

        $container = new TestContainer();
        $container->set('alpha', $this->connectionAlpha);
        $container->set('beta', $this->connectionBeta);
        $container->set('default', $this->connectionDefault);
        ORMContext::bootstrap($container);
    }

    protected function tearDown(): void
    {
        ActiveRecord::$db = null;
        $this->resetModelSingletons();
        ORMContext::bootstrap(new TestContainer());
    }

    /**
     * Drops the cached per-class model singletons (and, with them, the
     * connections cached on ActiveRecord::$_connection).
     */
    private function resetModelSingletons(): void
    {
        (new ReflectionProperty(ActiveRecord::class, '_models'))->setValue(null, []);
    }

    // ===============================================================
    //  #[Table]
    // ===============================================================

    public function testTableAttributeOverridesTableName(): void
    {
        $this->assertSame('attr_custom_table', AttrTable::model()->tableName());
    }

    public function testTableNameFallsBackToShortClassName(): void
    {
        $this->assertSame('AttrPlain', AttrPlain::model()->tableName());
    }

    public function testTableAndDatabaseAttributesWorkTogether(): void
    {
        $this->assertSame('attr_alpha_table', AttrBoth::model()->tableName());
        $this->assertSame('alpha', AttrBoth::model()->databaseName());
    }

    // ===============================================================
    //  #[Database]
    // ===============================================================

    public function testDatabaseAttributeOverridesDatabaseName(): void
    {
        $this->assertSame('alpha', AttrAlpha::model()->databaseName());
        $this->assertSame('beta', AttrBeta::model()->databaseName());
    }

    public function testDatabaseNameDefaultsToDefault(): void
    {
        $this->assertSame('default', AttrPlain::model()->databaseName());
    }

    // ===============================================================
    //  Connection resolution (issue #1)
    // ===============================================================

    public function testEachModelResolvesItsOwnConnection(): void
    {
        // Resolve beta first: the old implementation cached the first connection
        // in the shared ActiveRecord::$db and returned it for every other model.
        $this->assertSame($this->connectionBeta, AttrBeta::model()->getDbConnection());
        $this->assertSame($this->connectionAlpha, AttrAlpha::model()->getDbConnection());

        $this->assertNotSame($this->connectionAlpha, $this->connectionBeta);

        // Resolution must not leak into the global override.
        $this->assertNull(ActiveRecord::$db);
    }

    public function testResolvedConnectionIsReused(): void
    {
        $first = AttrAlpha::model()->getDbConnection();

        $this->assertSame($this->connectionAlpha, $first);
        $this->assertSame($first, AttrAlpha::model()->getDbConnection());
    }

    public function testGlobalDbOverrideTakesPrecedence(): void
    {
        $override = new DbConnection('sqlite::memory:', '', '', 'override', autoConnect: false);
        ActiveRecord::$db = $override;

        $this->assertSame($override, AttrAlpha::model()->getDbConnection());
        $this->assertSame($override, AttrBeta::model()->getDbConnection());
    }

    public function testWrongServiceTypeThrows(): void
    {
        $container = new TestContainer();
        $container->set('alpha', new \stdClass());
        ORMContext::bootstrap($container);

        $this->expectException(DbException::class);

        AttrAlpha::model()->getDbConnection();
    }

    public function testMissingServiceIsReported(): void
    {
        // No 'alpha' service registered.
        ORMContext::bootstrap(new TestContainer());

        $this->expectException(NotFoundExceptionInterface::class);

        AttrAlpha::model()->getDbConnection();
    }
}

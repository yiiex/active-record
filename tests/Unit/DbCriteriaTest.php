<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yii1x\ActiveRecord\Db\Schema\DbCriteria;

/**
 * Unit tests for DbCriteria.
 * No database connection required - pure value object testing.
 */
class DbCriteriaTest extends TestCase
{
    protected function setUp(): void
    {
        DbCriteria::$paramCount = 0;
    }

    public function testConstructor(): void
    {
        $criteria = new DbCriteria([
            'select' => 'id, name',
            'condition' => 'status = 1',
            'limit' => 10,
        ]);

        $this->assertSame('id, name', $criteria->select);
        $this->assertSame('status = 1', $criteria->condition);
        $this->assertEquals(10, $criteria->limit);
    }

    // ---------------------------------------------------------------
    //  addCondition
    // ---------------------------------------------------------------

    public function testAddCondition(): void
    {
        $criteria = new DbCriteria();

        $criteria->addCondition('status = 1');
        $this->assertSame('status = 1', $criteria->condition);

        $criteria->addCondition('active = 1');
        $this->assertSame('(status = 1) AND (active = 1)', $criteria->condition);

        $criteria->addCondition('deleted = 0', 'OR');
        $this->assertSame('((status = 1) AND (active = 1)) OR (deleted = 0)', $criteria->condition);
    }

    public function testAddConditionArray(): void
    {
        $criteria = new DbCriteria();

        $criteria->addCondition(['status = 1', 'active = 1']);
        $this->assertSame('(status = 1) AND (active = 1)', $criteria->condition);

        $criteria->addCondition(['deleted = 0', 'archived = 0'], 'OR');
        $this->assertSame('((status = 1) AND (active = 1)) OR ((deleted = 0) OR (archived = 0))', $criteria->condition);
    }

    // ---------------------------------------------------------------
    //  addInCondition
    // ---------------------------------------------------------------

    public function testAddInCondition(): void
    {
        $criteria = new DbCriteria();

        $criteria->addInCondition('id', [1, 2, 3]);
        $this->assertSame('id IN (:ycp0, :ycp1, :ycp2)', $criteria->condition);
        $this->assertEquals([1, 2, 3], array_values($criteria->params));
    }

    public function testAddInConditionEmpty(): void
    {
        $criteria = new DbCriteria();

        $criteria->addInCondition('id', []);
        $this->assertSame('0=1', $criteria->condition);
        $this->assertEmpty($criteria->params);
    }

    public function testAddInConditionSingle(): void
    {
        $criteria = new DbCriteria();

        $criteria->addInCondition('id', [1]);
        $this->assertSame('id=:ycp0', $criteria->condition);
        $this->assertEquals(1, $criteria->params[':ycp0']);
    }

    public function testAddInConditionWithOperator(): void
    {
        $criteria = new DbCriteria();
        $criteria->condition = 'status = 1';

        $criteria->addInCondition('id', [1, 2], 'OR');
        $this->assertSame('(status = 1) OR (id IN (:ycp0, :ycp1))', $criteria->condition);
    }

    // ---------------------------------------------------------------
    //  addNotInCondition
    // ---------------------------------------------------------------

    public function testAddNotInCondition(): void
    {
        $criteria = new DbCriteria();

        $criteria->addNotInCondition('id', [1, 2, 3]);
        $this->assertSame('id NOT IN (:ycp0, :ycp1, :ycp2)', $criteria->condition);
        $this->assertEquals([1, 2, 3], array_values($criteria->params));
    }

    public function testAddNotInConditionEmpty(): void
    {
        $criteria = new DbCriteria();

        $criteria->addNotInCondition('id', []);
        $this->assertSame('', $criteria->condition);
        $this->assertEmpty($criteria->params);
    }

    // ---------------------------------------------------------------
    //  addColumnCondition
    // ---------------------------------------------------------------

    public function testAddColumnCondition(): void
    {
        $criteria = new DbCriteria();

        $criteria->addColumnCondition(['status' => 1, 'active' => 1]);
        $this->assertSame('status=:ycp0 AND active=:ycp1', $criteria->condition);
        $this->assertEquals(1, $criteria->params[':ycp0']);
        $this->assertEquals(1, $criteria->params[':ycp1']);
    }

    public function testAddColumnConditionWithNull(): void
    {
        $criteria = new DbCriteria();

        $criteria->addColumnCondition(['status' => 1, 'deleted_at' => null]);
        $this->assertSame('status=:ycp0 AND deleted_at IS NULL', $criteria->condition);
        $this->assertEquals(1, $criteria->params[':ycp0']);
    }

    public function testAddColumnConditionWithOr(): void
    {
        $criteria = new DbCriteria();

        $criteria->addColumnCondition(['status' => 1, 'active' => 1], 'OR');

        $this->assertStringContainsString('OR', $criteria->condition);
        $this->assertStringContainsString('status', $criteria->condition);
        $this->assertStringContainsString('active', $criteria->condition);
    }

    // ---------------------------------------------------------------
    //  addSearchCondition
    // ---------------------------------------------------------------

    public function testAddSearchCondition(): void
    {
        $criteria = new DbCriteria();

        $criteria->addSearchCondition('name', 'test');
        $this->assertSame('name LIKE :ycp0', $criteria->condition);
        $this->assertSame('%test%', $criteria->params[':ycp0']);
    }

    public function testAddSearchConditionNoEscape(): void
    {
        $criteria = new DbCriteria();

        $criteria->addSearchCondition('name', 'test%', false);
        $this->assertSame('name LIKE :ycp0', $criteria->condition);
        $this->assertSame('test%', $criteria->params[':ycp0']);
    }

    public function testAddSearchConditionEscape(): void
    {
        $criteria = new DbCriteria();

        $criteria->addSearchCondition('name', 'test%', true);
        $this->assertSame('name LIKE :ycp0', $criteria->condition);
        $this->assertSame('%test\\%%', $criteria->params[':ycp0']);
    }

    public function testAddSearchConditionNotLike(): void
    {
        $criteria = new DbCriteria();

        $criteria->addSearchCondition('name', 'test', true, 'AND', 'NOT LIKE');
        $this->assertSame('name NOT LIKE :ycp0', $criteria->condition);
        $this->assertSame('%test%', $criteria->params[':ycp0']);
    }

    // ---------------------------------------------------------------
    //  addBetweenCondition
    // ---------------------------------------------------------------

    public function testAddBetweenCondition(): void
    {
        $criteria = new DbCriteria();

        $criteria->addBetweenCondition('price', 10, 100);
        $this->assertSame('price BETWEEN :ycp0 AND :ycp1', $criteria->condition);
        $this->assertEquals(10, $criteria->params[':ycp0']);
        $this->assertEquals(100, $criteria->params[':ycp1']);
    }

    public function testAddBetweenConditionWithOperator(): void
    {
        $criteria = new DbCriteria();
        $criteria->condition = 'status = 1';

        $criteria->addBetweenCondition('price', 10, 100, 'OR');
        $this->assertSame('(status = 1) OR (price BETWEEN :ycp0 AND :ycp1)', $criteria->condition);
    }

    // ---------------------------------------------------------------
    //  compare
    // ---------------------------------------------------------------

    public function testCompareString(): void
    {
        $criteria = new DbCriteria();

        $criteria->compare('name', 'John');
        $this->assertSame('name=:ycp0', $criteria->condition);
        $this->assertSame('John', $criteria->params[':ycp0']);
    }

    public function testCompareStringWithOperator(): void
    {
        $criteria = new DbCriteria();

        $criteria->compare('age', '>18');
        $this->assertSame('age>:ycp0', $criteria->condition);
        $this->assertEquals(18, $criteria->params[':ycp0']);
    }

    public function testCompareArray(): void
    {
        $criteria = new DbCriteria();

        $criteria->compare('status', [1, 2, 3]);
        $this->assertSame('status IN (:ycp0, :ycp1, :ycp2)', $criteria->condition);
        $this->assertEquals([1, 2, 3], array_values($criteria->params));
    }

    public function testCompareEmpty(): void
    {
        $criteria = new DbCriteria();
        $criteria->condition = 'existing = 1';

        $criteria->compare('name', '');
        $this->assertSame('existing = 1', $criteria->condition); // unchanged
    }

    public function testComparePartialMatch(): void
    {
        $criteria = new DbCriteria();

        $criteria->compare('name', 'test', true);
        $this->assertSame('name LIKE :ycp0', $criteria->condition);
        $this->assertSame('%test%', $criteria->params[':ycp0']);
    }

    // ---------------------------------------------------------------
    //  mergeWith
    // ---------------------------------------------------------------

    public function testMergeWith(): void
    {
        $criteria1 = new DbCriteria();
        $criteria1->condition = 'status = 1';
        $criteria1->params[':status'] = 1;

        $criteria2 = new DbCriteria();
        $criteria2->condition = 'active = 1';
        $criteria2->limit = 10;

        $criteria1->mergeWith($criteria2);

        $this->assertSame('(status = 1) AND (active = 1)', $criteria1->condition);
        $this->assertEquals(10, $criteria1->limit);
    }

    public function testMergeWithOr(): void
    {
        $criteria1 = new DbCriteria();
        $criteria1->condition = 'status = 1';

        $criteria2 = new DbCriteria();
        $criteria2->condition = 'active = 1';

        $criteria1->mergeWith($criteria2, 'OR');

        $this->assertSame('(status = 1) OR (active = 1)', $criteria1->condition);
    }

    public function testMergeWithArray(): void
    {
        $criteria = new DbCriteria();
        $criteria->condition = 'status = 1';

        $criteria->mergeWith(['condition' => 'active = 1', 'limit' => 10]);

        $this->assertSame('(status = 1) AND (active = 1)', $criteria->condition);
        $this->assertEquals(10, $criteria->limit);
    }

    // ---------------------------------------------------------------
    //  toArray
    // ---------------------------------------------------------------

    public function testToArray(): void
    {
        $criteria = new DbCriteria();
        $criteria->select = 'id, name';
        $criteria->condition = 'status = 1';
        $criteria->limit = 10;

        $array = $criteria->toArray();

        $this->assertIsArray($array);
        $this->assertSame('id, name', $array['select']);
        $this->assertSame('status = 1', $array['condition']);
        $this->assertEquals(10, $array['limit']);
    }
}

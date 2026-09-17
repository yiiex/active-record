<?php

declare(strict_types=1);

namespace Yii1x\ActiveRecord\Tests\Driver\Abstract;

abstract class AbstractDbCommandQueryBuilderTest extends AbstractDatabaseTest
{
    // ---------------------------------------------------------------
    //  SELECT
    // ---------------------------------------------------------------

    public function testSelectDefault(): void
    {
        $command = $this->connection->createCommand();
        $command->select();

        $this->assertSame('*', $command->getSelect());
    }

    public function testSelectString(): void
    {
        $command = $this->connection->createCommand();
        $command->select('id, username');

        // Just verify it's not empty and contains the columns
        $this->assertStringContainsString('id', $command->getSelect());
        $this->assertStringContainsString('username', $command->getSelect());
    }

    public function testSelectArray(): void
    {
        $command = $this->connection->createCommand();
        $command->select(['id', 'username']);

        $this->assertStringContainsString('id', $command->getSelect());
        $this->assertStringContainsString('username', $command->getSelect());
    }

    public function testSelectWithAlias(): void
    {
        $command = $this->connection->createCommand();
        $command->select(['id AS post_id', 'title']);

        $this->assertStringContainsString('AS', $command->getSelect());
        $this->assertStringContainsString('post_id', $command->getSelect());
    }

    // ---------------------------------------------------------------
    //  FROM
    // ---------------------------------------------------------------

    public function testFromDefault(): void
    {
        $command = $this->connection->createCommand();

        $this->assertSame('', $command->getFrom());
    }

    public function testFromString(): void
    {
        $command = $this->connection->createCommand();
        $command->from('posts');

        $this->assertStringContainsString('posts', $command->getFrom());
    }

    public function testFromArray(): void
    {
        $command = $this->connection->createCommand();
        $command->from(['posts', 'users']);

        $this->assertStringContainsString('posts', $command->getFrom());
        $this->assertStringContainsString('users', $command->getFrom());
    }

    public function testFromWithAlias(): void
    {
        $command = $this->connection->createCommand();
        $command->from('posts p');

        $this->assertStringContainsString('posts', $command->getFrom());
    }

    // ---------------------------------------------------------------
    //  WHERE
    // ---------------------------------------------------------------

    public function testWhereDefault(): void
    {
        $command = $this->connection->createCommand();

        $this->assertSame('', $command->getWhere());
        $this->assertSame([], $command->params);
    }

    public function testWhereString(): void
    {
        $command = $this->connection->createCommand();
        $command->where('id = :id', [':id' => 1]);

        $this->assertStringContainsString('id', $command->getWhere());
        $this->assertEquals([':id' => 1], $command->params);
    }

    public function testWhereArrayAnd(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['and', 'id = 1', 'author_id = 2']);

        $this->assertStringContainsString('AND', $command->getWhere());
    }

    public function testWhereArrayOr(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['or', 'id = 1', 'id = 2']);

        $this->assertStringContainsString('OR', $command->getWhere());
    }

    public function testWhereArrayIn(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['in', 'id', [1, 2, 3]]);

        $this->assertStringContainsString('IN', $command->getWhere());
    }

    public function testWhereArrayInEmpty(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['in', 'id', []]);

        $this->assertSame('0=1', $command->getWhere());
    }

    public function testWhereArrayNotIn(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['not in', 'id', [1, 2, 3]]);

        $this->assertStringContainsString('NOT IN', $command->getWhere());
    }

    public function testWhereArrayLike(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['like', 'title', '%test%']);

        $this->assertStringContainsString('LIKE', $command->getWhere());
    }

    public function testWhereArrayLikeMultiple(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['like', 'title', ['%test%', '%post%']]);

        $where = $command->getWhere();
        $this->assertStringContainsString('LIKE', $where);
        $this->assertStringContainsString('AND', $where);
    }

    // ---------------------------------------------------------------
    //  AND WHERE / OR WHERE
    // ---------------------------------------------------------------

    public function testAndWhere(): void
    {
        $command = $this->connection->createCommand();
        $command->where('id = 1');
        $command->andWhere('author_id = 2');

        $where = $command->getWhere();
        $this->assertStringContainsString('id', $where);
        $this->assertStringContainsString('author_id', $where);
        $this->assertStringContainsString('AND', $where);
    }

    public function testOrWhere(): void
    {
        $command = $this->connection->createCommand();
        $command->where('id = 1');
        $command->orWhere('id = 2');

        $where = $command->getWhere();
        $this->assertStringContainsString('id', $where);
        $this->assertStringContainsString('OR', $where);
    }

    // ---------------------------------------------------------------
    //  JOIN
    // ---------------------------------------------------------------

    public function testJoinDefault(): void
    {
        $command = $this->connection->createCommand();

        $this->assertSame('', $command->getJoin());
    }

    public function testJoinInner(): void
    {
        $command = $this->connection->createCommand();
        $command->join('users', 'users.id = posts.author_id');

        $join = $command->getJoin();
        $this->assertIsArray($join);
        $this->assertCount(1, $join);
        $this->assertStringContainsString('JOIN', $join[0]);
        $this->assertStringContainsString('users', $join[0]);
    }

    public function testJoinLeft(): void
    {
        $command = $this->connection->createCommand();
        $command->leftJoin('users', 'users.id = posts.author_id');

        $join = $command->getJoin();
        $this->assertIsArray($join);
        $this->assertStringContainsString('LEFT JOIN', $join[0]);
    }

    public function testJoinRight(): void
    {
        $command = $this->connection->createCommand();
        $command->rightJoin('users', 'users.id = posts.author_id');

        $join = $command->getJoin();
        $this->assertIsArray($join);
        $this->assertStringContainsString('RIGHT JOIN', $join[0]);
    }

    public function testJoinCross(): void
    {
        $command = $this->connection->createCommand();
        $command->crossJoin('users');

        $join = $command->getJoin();
        $this->assertIsArray($join);
        $this->assertStringContainsString('CROSS JOIN', $join[0]);
    }

    public function testJoinNatural(): void
    {
        $command = $this->connection->createCommand();
        $command->naturalJoin('users');

        $join = $command->getJoin();
        $this->assertIsArray($join);
        $this->assertStringContainsString('NATURAL JOIN', $join[0]);
    }

    // ---------------------------------------------------------------
    //  GROUP BY
    // ---------------------------------------------------------------

    public function testGroupDefault(): void
    {
        $command = $this->connection->createCommand();

        $this->assertSame('', $command->getGroup());
    }

    public function testGroupString(): void
    {
        $command = $this->connection->createCommand();
        $command->group('author_id');

        $this->assertStringContainsString('author_id', $command->getGroup());
    }

    public function testGroupArray(): void
    {
        $command = $this->connection->createCommand();
        $command->group(['author_id', 'status']);

        $group = $command->getGroup();
        $this->assertStringContainsString('author_id', $group);
        $this->assertStringContainsString('status', $group);
    }

    // ---------------------------------------------------------------
    //  HAVING
    // ---------------------------------------------------------------

    public function testHavingDefault(): void
    {
        $command = $this->connection->createCommand();

        $this->assertSame('', $command->getHaving());
    }

    public function testHavingString(): void
    {
        $command = $this->connection->createCommand();
        $command->having('COUNT(*) > 5');

        $this->assertStringContainsString('COUNT', $command->getHaving());
    }

    // ---------------------------------------------------------------
    //  ORDER BY
    // ---------------------------------------------------------------

    public function testOrderDefault(): void
    {
        $command = $this->connection->createCommand();

        $this->assertSame('', $command->getOrder());
    }

    public function testOrderString(): void
    {
        $command = $this->connection->createCommand();
        $command->order('id DESC');

        $this->assertStringContainsString('id', $command->getOrder());
        $this->assertStringContainsString('DESC', $command->getOrder());
    }

    public function testOrderArray(): void
    {
        $command = $this->connection->createCommand();
        $command->order(['id DESC', 'title ASC']);

        $order = $command->getOrder();
        $this->assertStringContainsString('id', $order);
        $this->assertStringContainsString('title', $order);
    }

    // ---------------------------------------------------------------
    //  LIMIT / OFFSET
    // ---------------------------------------------------------------

    public function testLimitDefault(): void
    {
        $command = $this->connection->createCommand();

        $this->assertEquals(-1, $command->getLimit());
    }

    public function testLimit(): void
    {
        $command = $this->connection->createCommand();
        $command->limit(10);

        $this->assertEquals(10, $command->getLimit());
    }

    public function testLimitWithOffset(): void
    {
        $command = $this->connection->createCommand();
        $command->limit(10, 20);

        $this->assertEquals(10, $command->getLimit());
        $this->assertEquals(20, $command->getOffset());
    }

    public function testOffsetDefault(): void
    {
        $command = $this->connection->createCommand();

        $this->assertEquals(-1, $command->getOffset());
    }

    public function testOffset(): void
    {
        $command = $this->connection->createCommand();
        $command->offset(20);

        $this->assertEquals(20, $command->getOffset());
    }

    // ---------------------------------------------------------------
    //  UNION
    // ---------------------------------------------------------------

    public function testUnionDefault(): void
    {
        $command = $this->connection->createCommand();

        $this->assertSame('', $command->getUnion());
    }

    public function testUnionString(): void
    {
        $command = $this->connection->createCommand();
        $command->union('SELECT id FROM comments');

        $union = $command->getUnion();
        $this->assertIsArray($union);
        $this->assertCount(1, $union);
    }

    public function testUnionMultiple(): void
    {
        $command = $this->connection->createCommand();
        $command->union('SELECT id FROM comments');
        $command->union('SELECT id FROM categories');

        $union = $command->getUnion();
        $this->assertIsArray($union);
        $this->assertCount(2, $union);
    }

    // ---------------------------------------------------------------
    //  Full query execution
    // ---------------------------------------------------------------

    public function testFullQuery(): void
    {
        $rows = $this->connection->createCommand()
            ->select('id, title, author_id')
            ->from('posts')
            ->where('author_id = :author', [':author' => 2])
            ->order('id DESC')
            ->limit(2, 1)
            ->queryAll();

        // Should get 2 rows (LIMIT 2)
        $this->assertCount(2, $rows);

        // All should have author_id = 2
        foreach ($rows as $row) {
            $this->assertEquals(2, $row['author_id']);
        }

        // First should have higher id than second (ORDER BY id DESC)
        $this->assertGreaterThan($rows[1]['id'], $rows[0]['id']);
    }

    public function testArraySyntax(): void
    {
        $command = $this->connection->createCommand([
            'select' => 'username, password',
            'from' => 'users',
            'where' => 'email = :email',
            'params' => [':email' => 'user2@example.com'],
            'order' => 'username DESC',
        ]);

        $row = $command->queryRow();

        $this->assertSame('user2', $row['username']);
        $this->assertSame('pass2', $row['password']);
    }
}

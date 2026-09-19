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

        $this->assertSame(
            $this->connection->quoteColumnName('id') . ', ' . $this->connection->quoteColumnName('username'),
            $command->getSelect()
        );
    }

    public function testSelectArray(): void
    {
        $command = $this->connection->createCommand();
        $command->select(['id', 'username']);

        $this->assertSame(
            $this->connection->quoteColumnName('id') . ', ' . $this->connection->quoteColumnName('username'),
            $command->getSelect()
        );
    }

    public function testSelectWithAlias(): void
    {
        $command = $this->connection->createCommand();
        $command->select(['id AS post_id', 'title']);

        $this->assertSame(
            $this->connection->quoteColumnName('id') . ' AS ' . $this->connection->quoteColumnName('post_id')
            . ', ' . $this->connection->quoteColumnName('title'),
            $command->getSelect()
        );
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

        $this->assertSame($this->connection->quoteTableName('posts'), $command->getFrom());
    }

    public function testFromArray(): void
    {
        $command = $this->connection->createCommand();
        $command->from(['posts', 'users']);

        $this->assertSame(
            $this->connection->quoteTableName('posts') . ', ' . $this->connection->quoteTableName('users'),
            $command->getFrom()
        );
    }

    public function testFromWithAlias(): void
    {
        $command = $this->connection->createCommand();
        $command->from('posts p');

        $this->assertSame(
            $this->connection->quoteTableName('posts') . ' ' . $this->connection->quoteTableName('p'),
            $command->getFrom()
        );
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

        $this->assertSame('id = :id', $command->getWhere());
        $this->assertSame([':id' => 1], $command->params);
    }

    public function testWhereArrayAnd(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['and', 'id = 1', 'author_id = 2']);

        $this->assertSame('(id = 1) AND (author_id = 2)', $command->getWhere());
    }

    public function testWhereArrayOr(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['or', 'id = 1', 'id = 2']);

        $this->assertSame('(id = 1) OR (id = 2)', $command->getWhere());
    }

    public function testWhereArrayIn(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['in', 'id', [1, 2, 3]]);

        $this->assertSame($this->connection->quoteColumnName('id') . ' IN (1, 2, 3)', $command->getWhere());
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

        $this->assertSame($this->connection->quoteColumnName('id') . ' NOT IN (1, 2, 3)', $command->getWhere());
    }

    public function testWhereArrayLike(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['like', 'title', '%test%']);

        $this->assertSame(
            $this->connection->quoteColumnName('title') . ' LIKE ' . $this->connection->quoteValue('%test%'),
            $command->getWhere()
        );
    }

    public function testWhereArrayLikeMultiple(): void
    {
        $command = $this->connection->createCommand();
        $command->where(['like', 'title', ['%test%', '%post%']]);

        $title = $this->connection->quoteColumnName('title');
        $this->assertSame(
            $title . ' LIKE ' . $this->connection->quoteValue('%test%')
            . ' AND ' . $title . ' LIKE ' . $this->connection->quoteValue('%post%'),
            $command->getWhere()
        );
    }

    // ---------------------------------------------------------------
    //  AND WHERE / OR WHERE
    // ---------------------------------------------------------------

    public function testAndWhere(): void
    {
        $command = $this->connection->createCommand();
        $command->where('id = 1');
        $command->andWhere('author_id = 2');

        $this->assertSame('(id = 1) AND (author_id = 2)', $command->getWhere());
    }

    public function testOrWhere(): void
    {
        $command = $this->connection->createCommand();
        $command->where('id = 1');
        $command->orWhere('id = 2');

        $this->assertSame('(id = 1) OR (id = 2)', $command->getWhere());
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
        $this->assertSame(
            'JOIN ' . $this->connection->quoteTableName('users') . ' ON users.id = posts.author_id',
            $join[0]
        );
    }

    public function testJoinLeft(): void
    {
        $command = $this->connection->createCommand();
        $command->leftJoin('users', 'users.id = posts.author_id');

        $join = $command->getJoin();
        $this->assertIsArray($join);
        $this->assertSame(
            'LEFT JOIN ' . $this->connection->quoteTableName('users') . ' ON users.id = posts.author_id',
            $join[0]
        );
    }

    public function testJoinRight(): void
    {
        $command = $this->connection->createCommand();
        $command->rightJoin('users', 'users.id = posts.author_id');

        $join = $command->getJoin();
        $this->assertIsArray($join);
        $this->assertSame(
            'RIGHT JOIN ' . $this->connection->quoteTableName('users') . ' ON users.id = posts.author_id',
            $join[0]
        );
    }

    public function testJoinCross(): void
    {
        $command = $this->connection->createCommand();
        $command->crossJoin('users');

        $join = $command->getJoin();
        $this->assertIsArray($join);
        $this->assertSame('CROSS JOIN ' . $this->connection->quoteTableName('users'), $join[0]);
    }

    public function testJoinNatural(): void
    {
        $command = $this->connection->createCommand();
        $command->naturalJoin('users');

        $join = $command->getJoin();
        $this->assertIsArray($join);
        $this->assertSame('NATURAL JOIN ' . $this->connection->quoteTableName('users'), $join[0]);
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

        $this->assertSame($this->connection->quoteColumnName('author_id'), $command->getGroup());
    }

    public function testGroupArray(): void
    {
        $command = $this->connection->createCommand();
        $command->group(['author_id', 'status']);

        $this->assertSame(
            $this->connection->quoteColumnName('author_id') . ', ' . $this->connection->quoteColumnName('status'),
            $command->getGroup()
        );
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

        $this->assertSame('COUNT(*) > 5', $command->getHaving());
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

        $this->assertSame($this->connection->quoteColumnName('id') . ' DESC', $command->getOrder());
    }

    public function testOrderArray(): void
    {
        $command = $this->connection->createCommand();
        $command->order(['id DESC', 'title ASC']);

        $this->assertSame(
            $this->connection->quoteColumnName('id') . ' DESC, '
            . $this->connection->quoteColumnName('title') . ' ASC',
            $command->getOrder()
        );
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

        $this->assertSame(['SELECT id FROM comments'], $command->getUnion());
    }

    public function testUnionMultiple(): void
    {
        $command = $this->connection->createCommand();
        $command->union('SELECT id FROM comments');
        $command->union('SELECT id FROM categories');

        $this->assertSame(
            ['SELECT id FROM comments', 'SELECT id FROM categories'],
            $command->getUnion()
        );
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

        $this->assertCount(2, $rows);

        foreach ($rows as $row) {
            $this->assertEquals(2, $row['author_id']);
        }

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

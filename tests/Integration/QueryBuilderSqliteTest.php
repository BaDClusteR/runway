<?php

declare(strict_types=1);

namespace Runway\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionComparisonOperatorsEnum;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionComparison;
use Runway\DataStorage\QueryBuilder\IQueryBuilder;
use Runway\Singleton\Container;
use Runway\Tests\Fixture\Model\Post;
use Runway\Tests\Fixture\Model\User;
use Runway\Tests\Support\ContainerState;
use Runway\Tests\Support\Database;

/**
 * The SQL of the query builder on SQLite: the portable syntax.
 */
class QueryBuilderSqliteTest extends TestCase {
    protected function setUp(): void {
        $driver = ContainerState::reset()->getDataStorageDriver();
        Database::createTables($driver);

        foreach (['Ann', 'Bob', 'Cid', 'Dan', 'Eve'] as $index => $name) {
            $driver->execute('INSERT INTO `{users}` (name, score) VALUES (:name, :score)', ['name' => $name, 'score' => $index]);
        }

        $driver->execute("INSERT INTO `{posts}` (title, author_id) VALUES ('A', 1), ('B', 1), ('C', 2)");
    }

    protected function tearDown(): void {
        ContainerState::reset();
    }

    public function testLimitAndOffset(): void {
        $names = $this->qb()->select('name')->from('users')->orderBy('id', 'ASC')->setLimit(2, 1)->getResults();

        self::assertSame([['name' => 'Bob'], ['name' => 'Cid']], $names);
    }

    public function testCountOfPage(): void {
        $qb = $this->qb()->select()->from('users')->where('score > 0')->orderBy('id', 'DESC')->setLimit(2, 2);

        self::assertSame(4, $qb->count());
        self::assertCount(2, $qb->getResults());
    }

    public function testConditions(): void {
        $qb = $this->qb()->select('name')->from('users');
        $expr = $qb->expr();

        $qb->where(new ExpressionComparison('name', ExpressionComparisonOperatorsEnum::LIKE, ':pattern'))
           ->orWhere($expr->and($expr->in('id', [':id_0', ':id_1']), $expr->isNotNull('score')))
           ->setVariables(['pattern' => 'D%', 'id_0' => 4, 'id_1' => 5])
           ->orderBy('name', 'ASC');

        self::assertSame([['name' => 'Dan'], ['name' => 'Eve']], $qb->getResults());
    }

    public function testJoinAndGroup(): void {
        $qb = $this->qb()->select('u.name', 'COUNT(p.id) AS posts')->from('users', 'u');

        $rows = $qb->leftJoin('posts', 'p', condition: $qb->expr()->eq('p.author_id', 'u.id'))
                   ->groupBy('u.id')
                   ->having('COUNT(p.id) > 0')
                   ->orderBy('posts', 'DESC')
                   ->getResults();

        self::assertSame([['name' => 'Ann', 'posts' => 2], ['name' => 'Bob', 'posts' => 1]], $rows);
    }

    public function testInsertUpdateDelete(): void {
        $qb = $this->qb()->insert()->into('posts', ['title', 'author_id'])
                   ->values([[':t0', ':a0'], [':t1', ':a1']])
                   ->setVariables(['t0' => 'D', 'a0' => 3, 't1' => 'E', 'a1' => 3]);
        $qb->execute();
        self::assertSame('5', $qb->getLastInsertId());

        $this->qb()->insert()->into('posts')->set('title', ':title')->setVariable('title', 'F')->execute();
        $this->qb()->update('posts')->set('title', ':title')->where('author_id = 3')->setVariable('title', 'G')->execute();
        $this->qb()->delete()->from('posts')->where('author_id = 1')->execute();

        self::assertSame(
            ['C', 'F', 'G', 'G'],
            array_column($this->qb()->select('title')->from('posts')->orderBy('title', 'ASC')->getResults(), 'title')
        );
    }

    public function testEntities(): void {
        $posts = $this->qb()->select()->from(Post::class)->orderBy('id', 'ASC')->getEntities();

        self::assertContainsOnlyInstancesOf(Post::class, $posts);
        self::assertSame(['A', 'B', 'C'], array_map(static fn(Post $post): string => $post->getTitle(), $posts));

        $user = $this->qb()->select()->from(User::class)->where('name = :name')->setVariable('name', 'Eve')->getFirstEntity();
        self::assertInstanceOf(User::class, $user);
        self::assertSame(4.0, $user->getScore());
    }

    protected function qb(): IQueryBuilder {
        return Container::getInstance()->getService(IQueryBuilder::class);
    }
}

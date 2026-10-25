<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\DataStorage;

use PHPUnit\Framework\TestCase;
use Runway\DataStorage\QueryBuilder\Converter\TableNameEscaper;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinConditionTypeEnum;
use Runway\DataStorage\QueryBuilder\Exception\QueryBuilderException;
use Runway\DataStorage\QueryBuilder\ExpressionBuilder\ExpressionBuilder;
use Runway\DataStorage\QueryBuilder\QueryBuilder;
use Runway\Model\Helper\IDataStoragePropertiesHelper;
use Runway\Singleton\Container;
use Runway\Tests\Fixture\Model\User;
use Runway\Tests\Support\ContainerState;
use Runway\Tests\Support\FakeDataStorageDriver;

class QueryBuilderTest extends TestCase {
    protected FakeDataStorageDriver $driver;

    protected function setUp(): void {
        $this->driver = new FakeDataStorageDriver();
    }

    public function testSelect(): void {
        $qb = $this->qb();

        self::assertSame('SELECT * FROM `{users}`', $qb->select()->from('users')->getSQL());
        self::assertSame('SELECT id, name FROM `{users}` u', $qb->select('id', 'name')->from('users', 'u')->getSQL());
        self::assertSame(
            'SELECT COUNT(id) FROM `{users}` INDEX BY id',
            $qb->select($qb->expr()->func('COUNT', 'id'))->from('users', '', 'id')->getSQL()
        );
    }

    public function testSelectIsTheDefaultOperation(): void {
        self::assertSame('SELECT * FROM `{users}`', $this->qb()->from('users')->getSQL());
    }

    public function testFromEntity(): void {
        $qb = $this->qb()->select()->from(User::class);

        self::assertSame('SELECT * FROM `{users}`', $qb->getSQL());
        self::assertSame(User::class, $qb->getEntityFQN());
    }

    public function testWhere(): void {
        $qb = $this->qb()->select()->from('users');
        $expr = $qb->expr();

        $qb->where($expr->eq('id', ':id'))
           ->andWhere($expr->or($expr->gt('age', 18), $expr->isNull('banned')))
           ->orWhere('admin = 1');

        self::assertSame(
            'SELECT * FROM `{users}` WHERE id = :id AND (age > 18 OR (banned IS NULL)) OR admin = 1',
            $qb->getSQL()
        );

        self::assertSame('SELECT * FROM `{users}` WHERE a = 1', $qb->where('a = 1')->getSQL());
        self::assertSame('SELECT * FROM `{users}`', $qb->clearWhere()->getSQL());
        self::assertSame('SELECT * FROM `{users}` WHERE b = 2', $qb->andWhere('b = 2')->getSQL());
        self::assertSame('SELECT * FROM `{users}` WHERE c = 3', $qb->clearWhere()->orWhere('c = 3')->getSQL());
    }

    public function testJoins(): void {
        $qb = $this->qb()->select()->from('users', 'u');
        $expr = $qb->expr();

        $qb->leftJoin('posts', 'p', ExpressionJoinConditionTypeEnum::ON, $expr->eq('p.author_id', 'u.id'))
           ->innerJoin('roles', 'r', condition: 'r.id = u.role_id')
           ->rightJoin('groups', 'g', ExpressionJoinConditionTypeEnum::WITH, 'g.id = u.group_id', 'g.id')
           ->crossJoin('settings')
           ->selfJoin('users', 'parent', condition: 'parent.id = u.parent_id');

        self::assertSame(
            'SELECT * FROM `{users}` u LEFT JOIN `{posts}` p ON (p.author_id = u.id) INNER JOIN `{roles}` r ON (r.id = u.role_id)'
            . ' RIGHT JOIN `{groups}` g WITH (g.id = u.group_id) INDEX BY g.id CROSS JOIN `{settings}`'
            . ' SELF JOIN `{users}` parent ON (parent.id = u.parent_id)',
            $qb->getSQL()
        );
        self::assertSame('SELECT * FROM `{users}` u', $qb->clearJoin()->getSQL());
    }

    public function testGroupByHavingOrderByLimit(): void {
        $qb = $this->qb()->select('author_id', 'COUNT(*)')->from('posts');

        $qb->groupBy('author_id')
           ->addGroupBy('status')
           ->having('COUNT(*) > 1')
           ->andHaving('MAX(id) > 5')
           ->orHaving('author_id = 1')
           ->orderBy('author_id', 'desc')
           ->addOrderBy('status', 'ASC')
           ->setLimit(10, 20);

        self::assertSame(
            'SELECT author_id, COUNT(*) FROM `{posts}` GROUP BY author_id, status HAVING COUNT(*) > 1 AND MAX(id) > 5'
            . ' OR author_id = 1 ORDER BY author_id DESC, status ASC LIMIT 10 OFFSET 20',
            $qb->getSQL()
        );

        $qb->clearGroupBy()->clearHaving()->clearOrderBy()->clearLimit();
        self::assertSame('SELECT author_id, COUNT(*) FROM `{posts}`', $qb->getSQL());

        $qb->addGroupBy('a')->andHaving('b')->addOrderBy('c', 'unknown')->setLimit(5);
        self::assertSame('SELECT author_id, COUNT(*) FROM `{posts}` GROUP BY a HAVING b ORDER BY c ASC LIMIT 5', $qb->getSQL());

        self::assertSame('SELECT author_id, COUNT(*) FROM `{posts}` GROUP BY a HAVING c ORDER BY d ASC LIMIT 5', $qb->clearHaving()->orHaving('c')->orderBy('d', 'ASC')->getSQL());
        self::assertSame('SELECT author_id, COUNT(*) FROM `{posts}` GROUP BY e HAVING c ORDER BY d ASC LIMIT 5', $qb->groupBy('e')->getSQL());
    }

    public function testLimitWithoutOffset(): void {
        self::assertSame('SELECT * FROM `{users}` LIMIT 3', $this->qb()->select()->from('users')->setLimit(3, 0)->getSQL());
    }

    public function testInsertRows(): void {
        $qb = $this->qb()->insert()
                   ->into('users', ['name', 'age', 'name'])
                   ->values([[':name_0', ':age_0']])
                   ->addValue([':name_1', ':age_1'])
                   ->addValues([[':name_2', 30]]);

        self::assertSame(
            'INSERT INTO `{users}` (`name`, `age`) VALUES (:name_0, :age_0), (:name_1, :age_1), (:name_2, 30)',
            $qb->getSQL()
        );

        $qb->columns(['a'])->addColumn('b')->addColumn('a')->addColumns(['c', 'b'])->clearValues()->addValue([1, 2, 3]);
        self::assertSame('INSERT INTO `{users}` (`a`, `b`, `c`) VALUES (1, 2, 3)', $qb->getSQL());
    }

    public function testInsertWithSet(): void {
        $qb = $this->qb()->insert()->into('users')->set('name', ':name')->addSet('age', 5);

        self::assertSame('INSERT INTO `{users}` (`name`, `age`) VALUES (:name, 5)', $qb->getSQL());
        self::assertSame('INSERT INTO `{users}`', $qb->clearSet()->getSQL());
        // The columns without rows are not enough.
        self::assertSame('INSERT INTO `{users}`', $qb->columns(['a'])->getSQL());
    }

    public function testUpdate(): void {
        $qb = $this->qb()->update('users')
                   ->set('name', ':name')
                   ->addSet('visits', $this->qb()->expr()->func('MAX', 'visits', 1))
                   ->where('id = :id');

        self::assertSame('UPDATE `{users}` SET `name` = :name, `visits` = MAX(visits, 1) WHERE id = :id', $qb->getSQL());
        self::assertSame('UPDATE `{users}` SET `name` = :other WHERE id = :id', $qb->set('name', ':other')->getSQL());
    }

    public function testDelete(): void {
        $qb = $this->qb()->delete()->from('users')->where('id = :id');

        self::assertSame('DELETE  FROM `{users}` WHERE id = :id', $qb->getSQL());
        self::assertSame('DELETE ', $qb->clearFrom()->clearWhere()->getSQL());
    }

    public function testQueriesGetVariables(): void {
        $this->driver->willReturn([['id' => 1]], [['id' => 2]], [['n' => 3]], [['id' => 4]]);

        $qb = $this->qb()->select()->from('users')
                   ->setVariable('a', 1)
                   ->setVariables(['b' => 2, 'a' => 3]);

        self::assertSame([['id' => 1]], $qb->getResults());
        self::assertSame(['id' => 2], $qb->getFirstResult());
        self::assertSame(3, $qb->getFirstScalarResult());
        self::assertSame([['id' => 4]], iterator_to_array($qb->iterate()));
        $qb->clearVariables()->execute();

        self::assertSame(
            [
                ['getResult', ['a' => 3, 'b' => 2]],
                ['getFirstResult', ['a' => 3, 'b' => 2]],
                ['getFirstScalarResult', ['a' => 3, 'b' => 2]],
                ['getResultsIterator', ['a' => 3, 'b' => 2]],
                ['execute', []],
            ],
            array_map(static fn(array $query): array => [$query['method'], $query['vars']], $this->driver->queries)
        );
        self::assertSame(array_fill(0, 5, 'SELECT * FROM `{users}`'), $this->driver->getQueries());
    }

    public function testCountIgnoresLimitAndOrder(): void {
        $this->driver->willReturn([['COUNT(*)' => '7']], [['COUNT(*)' => null]]);
        $qb = $this->qb()->update('users')->from('users')->where('a = 1')->orderBy('id', 'ASC')->setLimit(10, 20);

        self::assertSame(7, $qb->count());
        self::assertSame(0, $qb->count());
        self::assertSame('SELECT COUNT(*) FROM `{users}` WHERE a = 1', $this->driver->getQueries()[0]);
        // The query itself stays.
        self::assertSame('UPDATE `{users}` WHERE a = 1', $qb->getSQL());
        self::assertSame('SELECT * FROM `{users}` WHERE a = 1 ORDER BY id ASC LIMIT 10 OFFSET 20', $qb->select()->getSQL());
    }

    public function testLastInsertId(): void {
        $this->driver->lastInsertId = '15';

        self::assertSame('15', $this->qb()->getLastInsertId());
    }

    public function testDriverOfContainerByDefault(): void {
        $container = ContainerState::withDataStorageDriver($this->driver);
        $qb = new QueryBuilder(new TableNameEscaper(), new ExpressionBuilder(), $container->getService(IDataStoragePropertiesHelper::class));

        $qb->select('1')->execute();

        self::assertSame(['SELECT 1'], $this->driver->getQueries());

        $other = new FakeDataStorageDriver();
        $qb->setDataStorageDriver($other)->execute();
        self::assertSame(['SELECT 1'], $other->getQueries());
    }

    public function testEntities(): void {
        ContainerState::withDataStorageDriver($this->driver);
        $this->driver->willReturn(
            [['id' => 1, 'name' => 'Ann'], ['id' => 2, 'name' => 'Bob']],
            [['id' => 3, 'name' => 'Cid']],
            [['id' => 4, 'name' => 'Dan']],
            []
        );

        $qb = $this->qb()->select()->from(User::class);
        $users = $qb->getEntities();

        self::assertCount(2, $users);
        self::assertInstanceOf(User::class, $users[1]);
        self::assertSame('Bob', $users[1]->getName());

        $iterated = iterator_to_array($qb->iterateEntities());
        self::assertInstanceOf(User::class, $iterated[0]);
        self::assertSame(3, $iterated[0]->getId());

        $first = $qb->getFirstEntity();
        self::assertInstanceOf(User::class, $first);
        self::assertSame('Dan', $first->getName());
        self::assertNull($qb->getFirstEntity());
    }

    public function testEntitiesWithoutEntity(): void {
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage('Entity type is not set');

        $this->qb()->select()->from('users')->getEntities();
    }

    public function testIterateEntitiesWithoutEntity(): void {
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage('Entity type is not set');

        iterator_to_array($this->qb()->select()->from('users')->iterateEntities());
    }

    public function testEntityMustExist(): void {
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage("Entity 'App\Missing' does not exist");

        $this->qb()->setEntityFQN('App\Missing');
    }

    public function testEntityMustBeModel(): void {
        $this->expectException(QueryBuilderException::class);
        $this->expectExceptionMessage("Entity 'stdClass' does not implement Runway\Model\AEntity");

        $this->qb()->setEntityFQN(\stdClass::class);
    }

    public function testEntityCanBeReset(): void {
        self::assertSame('', $this->qb()->setEntityFQN(User::class)->setEntityFQN('')->getEntityFQN());
    }

    protected function qb(): QueryBuilder {
        return new QueryBuilder(
            new TableNameEscaper(),
            new ExpressionBuilder(),
            Container::getInstance()->getService(IDataStoragePropertiesHelper::class),
            $this->driver
        );
    }
}

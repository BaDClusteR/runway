<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Model;

use DateTime;
use PHPUnit\Framework\TestCase;
use Runway\Exception\RuntimeException;
use Runway\Model\Exception\EntityNotFoundException;
use Runway\Model\Exception\ModelException;
use Runway\Tests\Fixture\Model\Node;
use Runway\Tests\Fixture\Model\NoPrimaryProp;
use Runway\Tests\Fixture\Model\Post;
use Runway\Tests\Fixture\Model\Tag;
use Runway\Tests\Fixture\Model\User;
use Runway\Tests\Support\ContainerState;
use Runway\Tests\Support\FakeDataStorageDriver;

/**
 * The SQL of the models and the conversion of the values: the data storage is a fake one (see
 * Integration\ModelPersistenceTest for SQLite).
 */
class EntityTest extends TestCase {
    protected FakeDataStorageDriver $driver;

    protected function setUp(): void {
        $this->driver = new FakeDataStorageDriver();
        ContainerState::withDataStorageDriver($this->driver);
    }

    protected function tearDown(): void {
        ContainerState::reset();
    }

    public function testEntityByIdIsLoaded(): void {
        $this->driver->willReturn([$this->userRow()]);

        $user = new User(5);

        self::assertSame(['SELECT * FROM `{users}` WHERE id = :id'], $this->driver->getQueries());
        self::assertSame(['id' => 5], $this->driver->queries[0]['vars']);
        self::assertSame(5, $user->getId());
        self::assertSame('Ann', $user->getName());
        self::assertSame(1700000000, $user->getCreatedAt()?->getTimestamp());
        self::assertTrue($user->getIsActive());
        self::assertSame(['a', 'b'], $user->getTags());
        self::assertSame(4.5, $user->getScore());
        self::assertSame('annie', $user->getNickname());
        self::assertSame('hash', $user->getPasswordForTest());
        self::assertTrue($user->isPersistent());
        self::assertSame(5, $user->getUniqueIdentifier());
    }

    public function testMissingEntity(): void {
        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage(User::class . ' with ID 5 not found in the data storage.');

        new User(5);
    }

    public function testEntityMustHavePrimaryProp(): void {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage(NoPrimaryProp::class . ' does not have a primary property.');

        new NoPrimaryProp();
    }

    public function testNewEntity(): void {
        $user = new User();

        self::assertNull($user->getId());
        self::assertFalse($user->isPersistent());
        self::assertSame('', $user->getName());
        self::assertSame([], $this->driver->queries);
    }

    public function testMagicGettersAndSetters(): void {
        $user = new User();

        self::assertSame($user, $user->setName('Bob'));
        self::assertSame('Bob', $user->getName());
        // NoSetter: the default setter does nothing, NoGetter: the default getter gives null.
        $user->setNickname('bobby');
        $user->setPassword('hash');
        self::assertSame('', $user->getNickname());
        self::assertNull($user->__call('getPassword', []));
        self::assertSame('hash', $user->getPasswordForTest());
        // Not a prop.
        self::assertNull($user->__call('getNotStored', []));
        self::assertSame($user, $user->__call('setNotStored', ['value']));
        self::assertNull($user->__call('unknown', []));
        self::assertNull($user->__call('getUnknown', []));
    }

    public function testDateTimeOfSetter(): void {
        self::assertSame(1700000000, new User()->setCreatedAt(1700000000)->getCreatedAt()?->getTimestamp());
        self::assertSame(1700000000, new User()->setCreatedAt('2023-11-14T22:13:20+00:00')->getCreatedAt()?->getTimestamp());

        $date = new DateTime('@1600000000');
        self::assertSame($date, new User()->setCreatedAt($date)->getCreatedAt());
        self::assertNull(new User()->setCreatedAt(1)->setCreatedAt(null)->getCreatedAt());
    }

    public function testMalformedDateTimeOfSetter(): void {
        $this->expectException(\TypeError::class);

        new User()->setCreatedAt('not a date');
    }

    public function testExplicitSetterAndGetter(): void {
        $post = new Post();
        $post->set(['title' => '  Hello  ', 'unknown' => 'value']);

        self::assertSame('Hello', $post->getTitle());
        self::assertSame(['title' => 'Hello'], $post->get(['title', 'unknown']));
    }

    public function testToArray(): void {
        $this->driver->willReturn([$this->userRow()]);
        $user = new User(5);
        $post = new Post()->setTitle('Post');
        $post->setAuthor($user);

        self::assertSame(
            [
                'name'      => 'Ann',
                'createdAt' => date(DATE_ATOM, 1700000000),
                'isActive'  => true,
                'tags'      => 'a,b',
                'score'     => 4.5,
                'nickname'  => 'annie',
                // NoGetter: hidden.
                'password'  => null,
            ],
            $user->toArray()
        );
        self::assertSame(5, $user->toArray(true)['id']);
        self::assertSame('Post', $post->toArray()['title']);
        self::assertIsArray($post->toArray()['author']);
        self::assertSame('Ann', $post->toArray()['author']['name']);
    }

    public function testInsert(): void {
        $this->driver->lastInsertId = '7';
        $user = new User();
        $user->set([
            'name'      => 'Bob',
            'createdAt' => new DateTime('@1700000000'),
            'isActive'  => true,
            'tags'      => ['x' => 'й'],
            'score'     => 1.5,
            'password'  => 'hash',
        ]);

        $user->persist();

        self::assertSame(
            ['INSERT INTO `{users}` (`name`, `created_at`, `is_active`, `tags`, `score`, `nick`, `password`) VALUES'
             . ' (:name, :created_at, :is_active, :tags, :score, :nick, :password)'],
            $this->driver->getQueries()
        );
        self::assertSame(
            [
                'name'       => 'Bob',
                'created_at' => 1700000000,
                'is_active'  => 1,
                'tags'       => '{"x":"й"}',
                'score'      => 1.5,
                'nick'       => '',
                // NoGetter: saved anyway.
                'password'   => 'hash',
            ],
            $this->driver->queries[0]['vars']
        );
        self::assertSame(7, $user->getId());

        // Nothing has changed.
        $user->persist();
        self::assertCount(1, $this->driver->queries);
    }

    public function testUpdateOfChangedEntity(): void {
        $this->driver->willReturn([$this->userRow()]);
        $user = new User(5);

        $user->persist();
        self::assertCount(1, $this->driver->queries);

        $user->setName('Ann');
        $user->persist();
        // The same value is not a change.
        self::assertCount(1, $this->driver->queries);

        $user->setName('Anna');
        $user->persist();

        self::assertSame(
            'UPDATE `{users}` SET `name` = :name, `created_at` = :created_at, `is_active` = :is_active, `tags` = :tags,'
            . ' `score` = :score, `nick` = :nick, `password` = :password WHERE id = :__runway_id',
            $this->driver->queries[1]['query']
        );
        self::assertIsArray($this->driver->queries[1]['vars']);
        self::assertSame('Anna', $this->driver->queries[1]['vars']['name']);
        self::assertSame(5, $this->driver->queries[1]['vars']['__runway_id']);

        $user->persist();
        self::assertCount(2, $this->driver->queries);
    }

    public function testStringIdIsQueryVariable(): void {
        $code = "x' OR '1'='1";
        $this->driver->willReturn([['code' => $code, 'label' => 'old']]);
        $tag = Tag::findOne(['code' => $code]);
        self::assertInstanceOf(Tag::class, $tag);

        $tag->setLabel('new');
        $tag->persist();
        $tag->remove();

        self::assertSame(
            [
                'SELECT * FROM `{tags}` WHERE code = :code',
                'UPDATE `{tags}` SET `label` = :label WHERE code = :__runway_id',
                'DELETE  FROM `{tags}` WHERE code = :__runway_id',
            ],
            $this->driver->getQueries()
        );
        self::assertSame(['label' => 'new', '__runway_id' => $code], $this->driver->queries[1]['vars']);
        self::assertSame(['__runway_id' => $code], $this->driver->queries[2]['vars']);
    }

    public function testFind(): void {
        $this->driver->willReturn([$this->userRow(), $this->userRow(['id' => 6, 'name' => 'Bob'])]);

        $users = User::find(
            ['name' => 'Ann', 'createdAt' => null, 'id' => [5, '6'], 'isActive' => true, 'unknown' => 'x'],
            ['name', 'DESC'],
            [10, 20]
        );

        self::assertSame(
            'SELECT * FROM `{users}` WHERE name = :name AND (created_at IS NULL) AND id IN (:id_0, :id_1)'
            . ' AND is_active = :isActive ORDER BY name DESC LIMIT 10 OFFSET 20',
            $this->driver->queries[0]['query']
        );
        self::assertSame(['name' => 'Ann', 'id_0' => 5, 'id_1' => '6', 'isActive' => 1], $this->driver->queries[0]['vars']);
        self::assertSame(['Ann', 'Bob'], array_map(static fn(User $user): string => $user->getName(), $users));
    }

    public function testFindWithEmptyList(): void {
        User::find(['id' => []], 'name', 3);

        self::assertSame('SELECT * FROM `{users}` WHERE 1 = 0 ORDER BY name ASC LIMIT 3', $this->driver->queries[0]['query']);
    }

    public function testOrderByUnknownPropIsIgnored(): void {
        User::find([], 'password; DROP TABLE users');
        User::find([], ['nickname']);

        self::assertSame(['SELECT * FROM `{users}`', 'SELECT * FROM `{users}` ORDER BY nick ASC'], $this->driver->getQueries());
    }

    public function testIterateAndFindOne(): void {
        $this->driver->willReturn([$this->userRow()], [$this->userRow(['name' => 'Bob'])], []);

        $users = iterator_to_array(User::iterate(['name' => 'Ann'], limit: [5]));
        $user = User::findOne([], ['id', 'ASC']);

        self::assertCount(1, $users);
        self::assertSame('Ann', $users[0]->getName());
        self::assertSame('Bob', $user?->getName());
        self::assertNull(User::findOne());
        self::assertSame(
            ['SELECT * FROM `{users}` WHERE name = :name LIMIT 5', 'SELECT * FROM `{users}` ORDER BY id ASC', 'SELECT * FROM `{users}`'],
            $this->driver->getQueries()
        );
    }

    public function testFindByUniqueIdentifier(): void {
        $this->driver->willReturn([$this->userRow()]);

        self::assertSame('Ann', User::findByUniqueIdentifier(5)?->getName());
        self::assertNull(User::findByUniqueIdentifier(null));
        self::assertNull(User::findByUniqueIdentifier(0));
        self::assertSame(['SELECT * FROM `{users}` WHERE id = :id'], $this->driver->getQueries());
    }

    public function testRemove(): void {
        $this->driver->willReturn([$this->userRow()]);

        new User(5)->remove();

        self::assertSame('DELETE  FROM `{users}` WHERE id = :__runway_id', $this->driver->queries[1]['query']);
        self::assertSame(['__runway_id' => 5], $this->driver->queries[1]['vars']);
    }

    public function testReferencedEntity(): void {
        $this->driver->willReturn([['id' => 1, 'title' => 'Post', 'author_id' => '5']], [$this->userRow()]);

        $post = Post::findOne();

        $author = $post?->getAuthor();
        self::assertNotNull($author);
        self::assertSame(5, $author->getId());
        self::assertSame('Ann', $author->getName());
        self::assertSame(['SELECT * FROM `{posts}`', 'SELECT * FROM `{users}` WHERE id = :id'], $this->driver->getQueries());
    }

    public function testReferencedEntityIsSavedFirst(): void {
        $this->driver->lastInsertId = '3';
        $author = new User()->setName('Ann');
        $post = new Post()->setTitle('Post');
        $post->setAuthor($author);

        $post->persist();

        self::assertSame(
            ['INSERT INTO `{users}` (`name`, `created_at`, `is_active`, `tags`, `score`, `nick`, `password`) VALUES'
             . ' (:name, :created_at, :is_active, :tags, :score, :nick, :password)',
             'INSERT INTO `{posts}` (`title`, `author_id`) VALUES (:title, :author_id)'],
            $this->driver->getQueries()
        );
        self::assertSame(['title' => 'Post', 'author_id' => 3], $this->driver->queries[1]['vars']);
    }

    public function testReferences(): void {
        $this->driver->willReturn([$this->userRow()], [['id' => 1, 'title' => 'B', 'author_id' => null], ['id' => 2, 'title' => 'A', 'author_id' => null]]);
        $user = new User(5);

        $posts = $user->getPosts();

        self::assertSame(['B', 'A'], array_map(static fn(Post $post): string => $post->getTitle(), $posts));
        self::assertSame('SELECT * FROM `{posts}` WHERE author_id = :author ORDER BY title DESC', $this->driver->queries[1]['query']);
        self::assertSame(['author' => 5], $this->driver->queries[1]['vars']);
        // Loaded once.
        self::assertSame($posts, $user->getPosts());
        self::assertCount(2, $this->driver->queries);

        // The loaded references are saved with the entity.
        $posts[0]->setTitle('Changed');
        $user->persist();
        self::assertSame('UPDATE `{posts}` SET `title` = :title, `author_id` = :author_id WHERE id = :__runway_id', $this->driver->queries[2]['query']);
    }

    public function testNoReferencesOfNewEntity(): void {
        self::assertSame([], new User()->getPosts());
        self::assertSame([], $this->driver->queries);
    }

    public function testCircularReferenceOfNewEntities(): void {
        $first = new Node()->setName('first');
        $second = new Node()->setName('second');
        $first->setPartner($second);
        $second->setPartner($first);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Infinite loop while trying to persist entities.');

        $first->persist();
    }

    public function testGetterOfEntityIsUsed(): void {
        $this->driver->willReturn([['code' => 'a', 'label' => 'Old']]);
        $tag = Tag::findOne(['code' => 'a']);
        self::assertInstanceOf(Tag::class, $tag);

        self::assertSame('old', $tag->getLabel());
        self::assertSame(['label' => 'old'], $tag->toArray());
        self::assertSame(['code' => 'a', 'label' => 'old'], $tag->toArray(true));

        $tag->setLabel('NEW');
        $tag->persist();
        self::assertSame(['label' => 'new', '__runway_id' => 'a'], $this->driver->queries[1]['vars']);
    }

    public function testFailedPersistCanBeRepeated(): void {
        $user = new User()->setName('Ann');
        $this->driver->exception = new \RuntimeException('Connection lost');

        try {
            $user->persist();
            self::fail('No exception');
        } catch (\RuntimeException $e) {
            self::assertSame('Connection lost', $e->getMessage());
        }

        $this->driver->lastInsertId = '4';
        $user->persist();

        self::assertSame(4, $user->getId());
        self::assertCount(2, $this->driver->queries);
    }

    public function testChangeOfEntityThatIsNotLoaded(): void {
        $user = new User();
        $user->set(['id' => 5, 'name' => 'Ann']);

        $user->persist();

        self::assertStringStartsWith('UPDATE `{users}` SET', $this->driver->queries[0]['query']);
        self::assertIsArray($this->driver->queries[0]['vars']);
        self::assertSame(5, $this->driver->queries[0]['vars']['__runway_id']);
    }

    public function testNullValuesAreNotChange(): void {
        $this->driver->willReturn([$this->userRow(['created_at' => null, 'score' => null])]);
        $user = new User(5);

        $user->persist();

        self::assertCount(1, $this->driver->queries);
    }

    public function testListOfValuesWithKeys(): void {
        User::find(['id' => [3 => 5, 'a' => 6]], null, [1 => 10]);

        self::assertSame('SELECT * FROM `{users}` WHERE id IN (:id_0, :id_1) LIMIT 0 OFFSET 10', $this->driver->queries[0]['query']);
        self::assertSame(['id_0' => 5, 'id_1' => 6], $this->driver->queries[0]['vars']);
    }

    public function testQueryBuildersOfModel(): void {
        self::assertSame('SELECT * FROM `{users}` u', User::getQueryBuilder('u')->getSQL());
        self::assertSame('SELECT *', User::getPureQueryBuilder()->getSQL());
        self::assertNotSame(User::getPureQueryBuilder(), User::getPureQueryBuilder());
    }

    public function testMapIgnoresUnknownColumns(): void {
        $user = new User()->map(['name' => 'Ann', 'unknown' => 'x', 'notStored' => 'x']);

        self::assertSame('Ann', $user->getName());
        self::assertNull($user->getId());
    }

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    protected function userRow(array $override = []): array {
        return [
            'id'         => 5,
            'name'       => 'Ann',
            'created_at' => 1700000000,
            'is_active'  => 1,
            'tags'       => '["a","b"]',
            'score'      => '4.5',
            'nick'       => 'annie',
            'password'   => 'hash',
            ...$override,
        ];
    }
}

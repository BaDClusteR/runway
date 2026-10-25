<?php

declare(strict_types=1);

namespace Runway\Tests\Integration;

use DateTime;
use PHPUnit\Framework\TestCase;
use Runway\DataStorage\IDataStorageDriver;
use Runway\Exception\RuntimeException;
use Runway\Model\Exception\EntityNotFoundException;
use Runway\Tests\Fixture\Model\Node;
use Runway\Tests\Fixture\Model\Post;
use Runway\Tests\Fixture\Model\Tag;
use Runway\Tests\Fixture\Model\User;
use Runway\Tests\Support\ContainerState;
use Runway\Tests\Support\Database;

/**
 * The models on SQLite in memory (the DSN and the prefix of tests/config/test.env).
 */
class ModelPersistenceTest extends TestCase {
    protected IDataStorageDriver $driver;

    protected function setUp(): void {
        // A new container: a new connection, a new database.
        $this->driver = ContainerState::reset()->getDataStorageDriver();
        Database::createTables($this->driver);
    }

    protected function tearDown(): void {
        ContainerState::reset();
    }

    public function testSaveAndLoad(): void {
        $user = new User()->setName('Ann');
        $user->set([
            'createdAt' => new DateTime('@1700000000'),
            'isActive'  => true,
            'tags'      => ['admin', 'й'],
            'score'     => 4.5,
            'password'  => 'hash',
        ]);
        $user->setNicknameForTest('annie');
        $user->persist();

        self::assertSame(1, $user->getId());
        self::assertSame(
            ['id' => 1, 'name' => 'Ann', 'created_at' => 1700000000, 'is_active' => 1, 'tags' => '["admin","й"]',
             'score' => 4.5, 'nick' => 'annie', 'password' => 'hash'],
            $this->driver->getFirstResult('SELECT * FROM `{users}`')
        );

        $loaded = new User(1);
        self::assertSame('Ann', $loaded->getName());
        self::assertSame(1700000000, $loaded->getCreatedAt()?->getTimestamp());
        self::assertTrue($loaded->getIsActive());
        self::assertSame(['admin', 'й'], $loaded->getTags());
        self::assertSame(4.5, $loaded->getScore());
        self::assertSame('annie', $loaded->getNickname());
        self::assertSame('hash', $loaded->getPasswordForTest());
    }

    public function testUpdateAndRemove(): void {
        $user = new User()->setName('Ann');
        $user->persist();
        $other = new User()->setName('Bob');
        $other->persist();

        $user->setName('Anna');
        $user->persist();
        self::assertSame(['Anna', 'Bob'], $this->driver->getColumn('SELECT name FROM `{users}` ORDER BY id', [], 'name'));

        $user->remove();
        self::assertSame(['Bob'], $this->driver->getColumn('SELECT name FROM `{users}`', [], 'name'));

        $this->expectException(EntityNotFoundException::class);
        new User(1);
    }

    public function testSearch(): void {
        foreach (['Cid', 'Ann', 'Bob', 'Dan'] as $index => $name) {
            new User()->setName($name)->setIsActive($index % 2 === 0)->persist();
        }

        self::assertSame(['Ann', 'Bob', 'Cid', 'Dan'], $this->names(User::find([], 'name')));
        self::assertSame(['Bob', 'Cid'], $this->names(User::find([], ['name', 'ASC'], [2, 1])));
        self::assertSame(['Dan'], $this->names(User::find([], ['name', 'desc'], 1)));
        self::assertSame(['Bob', 'Cid'], $this->names(User::find(['isActive' => true], 'name')));
        self::assertSame(['Ann', 'Dan'], $this->names(User::find(['name' => ['Dan', 'Ann']], 'name')));
        self::assertSame([], User::find(['name' => []]));
        self::assertSame([], User::find(['createdAt' => new DateTime('@1')]));
        self::assertCount(4, User::find(['createdAt' => null]));
        self::assertSame('Ann', User::findOne(['name' => 'Ann'])?->getName());
        self::assertSame('Cid', User::findByUniqueIdentifier(1)?->getName());
        self::assertSame(['Cid', 'Ann', 'Bob', 'Dan'], $this->names(iterator_to_array(User::iterate())));
    }

    /**
     * @param User[] $users
     *
     * @return string[]
     */
    protected function names(array $users): array {
        return array_map(static fn(User $user): string => $user->getName(), $users);
    }

    public function testReferences(): void {
        $author = new User()->setName('Ann');
        $first = new Post()->setTitle('A');
        $first->setAuthor($author);
        // The author is saved with the post.
        $first->persist();
        $second = new Post()->setTitle('B');
        $second->setAuthor($author);
        $second->persist();
        new Post()->setTitle('Other')->persist();

        $loaded = new User((int)$author->getId());
        $titles = array_map(static fn(Post $post): string => $post->getTitle(), $loaded->getPosts());
        self::assertSame(['B', 'A'], $titles);
        self::assertSame('Ann', Post::findOne(['title' => 'A'])?->getAuthor()?->getName());
        self::assertSame(['A', 'B'], array_map(static fn(Post $post): string => $post->getTitle(), Post::find(['author' => $author], 'title')));

        // A loaded reference is saved with the entity.
        $loaded->getPosts()[0]->setTitle('B2');
        $loaded->persist();
        self::assertSame(['A', 'B2', 'Other'], $this->driver->getColumn('SELECT title FROM `{posts}` ORDER BY title', [], 'title'));
    }

    public function testStringIdIsNotPartOfQuery(): void {
        $this->driver->execute(
            'INSERT INTO `{tags}` (code, label) VALUES (:a, :label), (:b, :label)',
            ['a' => "x' OR '1'='1", 'b' => 'other', 'label' => 'old']
        );

        $tag = Tag::findOne(['code' => "x' OR '1'='1"]);
        self::assertInstanceOf(Tag::class, $tag);
        $tag->setLabel('new');
        $tag->persist();

        self::assertSame(
            [['code' => "x' OR '1'='1", 'label' => 'new'], ['code' => 'other', 'label' => 'old']],
            $this->driver->getResult('SELECT * FROM `{tags}` ORDER BY code DESC')
        );

        $tag->remove();
        self::assertSame(['other'], $this->driver->getColumn('SELECT code FROM `{tags}`', [], 'code'));
    }

    public function testCircularReferenceIsError(): void {
        $first = new Node()->setName('first');
        $second = new Node()->setName('second');
        $first->setPartner($second);
        $second->setPartner($first);

        try {
            $first->persist();
            self::fail('No exception');
        } catch (RuntimeException $e) {
            self::assertSame('Infinite loop while trying to persist entities.', $e->getMessage());
        }

        // A saved partner is fine.
        $saved = new Node()->setName('saved');
        $saved->persist();
        $node = new Node()->setName('node');
        $node->setPartner($saved);
        $node->persist();

        self::assertSame('saved', new Node((int)$node->getId())->getPartner()?->toArray()['name']);
    }
}

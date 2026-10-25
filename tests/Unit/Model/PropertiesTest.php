<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Model;

use DateTime;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Runway\Exception\RuntimeException;
use Runway\Model\Converter\DataStoragePropertiesConverter;
use Runway\Model\DataBuilder\DataStoragePropertyBuilder;
use Runway\Model\DTO\DataStoragePropertyDTO;
use Runway\Model\Exception\ModelException;
use Runway\Model\Helper\DataStoragePropertiesHelper;
use Runway\Model\Provider\DataStoragePropertiesProvider;
use Runway\Singleton\Converter;
use Runway\Tests\Fixture\Model\Node;
use Runway\Tests\Fixture\Model\Post;
use Runway\Tests\Fixture\Model\User;
use Runway\Tests\Support\ContainerState;
use Runway\Tests\Support\FakeDataStorageDriver;
use Runway\Tests\Support\LoggerSpy;

/**
 * The props of the fixture models: the attributes, the column names, the types of the data storage.
 */
class PropertiesTest extends TestCase {
    protected LoggerSpy $logger;

    protected function setUp(): void {
        $this->logger = new LoggerSpy();
    }

    public function testPropsOfAttributes(): void {
        $helper = $this->helper(User::class);

        self::assertSame(
            [
                // Prop, column, prop type, data storage type, primary, default getter, default setter.
                ['id', 'id', 'int', 'int', true, true, true],
                ['name', 'name', 'string', 'string', false, true, true],
                ['createdAt', 'created_at', 'DateTime', 'int', false, true, true],
                ['isActive', 'is_active', 'bool', 'int', false, true, true],
                ['tags', 'tags', 'array', 'string', false, true, true],
                ['score', 'score', 'float', 'float', false, true, true],
                ['nickname', 'nick', 'string', 'string', false, true, false],
                ['password', 'password', 'string', 'string', false, false, true],
            ],
            array_map(
                static fn(DataStoragePropertyDTO $prop): array => [
                    $prop->getPropName(),
                    $prop->getColumn(),
                    $prop->getPropType(),
                    $prop->getDataStorageType(),
                    $prop->isPrimary(),
                    $prop->isDefaultGetter(),
                    $prop->isDefaultSetter(),
                ],
                $helper->getProps()
            )
        );
        self::assertSame(['id', 'name', 'createdAt', 'isActive', 'tags', 'score', 'nickname', 'password'], $helper->getPropNames());
        self::assertSame('id', $helper->getPrimaryProp()?->getPropName());
        self::assertSame('users', $helper->getTableName());
    }

    public function testEntityPropIsIdColumn(): void {
        $prop = $this->helper(Post::class)->getPropByName('author');

        self::assertSame('author_id', $prop?->getColumn());
        self::assertSame(User::class, $prop->getPropType());
        self::assertSame('int', $prop->getDataStorageType());
        self::assertSame('partner_id', $this->helper(Node::class)->getPropByName('partner')?->getColumn());
    }

    public function testLookups(): void {
        $helper = $this->helper(User::class);

        self::assertSame('nickname', $helper->getPropNameByColumnName('nick'));
        self::assertSame('', $helper->getPropNameByColumnName('missing'));
        self::assertSame('created_at', $helper->getColumnNameByPropName('createdAt'));
        self::assertSame('', $helper->getColumnNameByPropName('missing'));
        self::assertSame('nickname', $helper->getPropByColumnName('nick')?->getPropName());
        self::assertNull($helper->getPropByColumnName('missing'));
        self::assertTrue($helper->isPropExists('createdAt'));
        self::assertFalse($helper->isPropExists('notStored'));
        self::assertFalse($helper->isPropExists('posts'));
    }

    public function testReferences(): void {
        $helper = $this->helper(User::class);
        $ref = $helper->getRefByPropName('posts');

        self::assertCount(1, $helper->getReferences());
        self::assertSame('posts', $ref?->propName);
        self::assertSame(Post::class, $ref->refModel);
        self::assertSame('author', $ref->refProp);
        self::assertSame(['title', 'DESC'], $ref->refOrderBy);
        self::assertNull($helper->getRefByPropName('name'));
    }

    public function testModelCanBeChanged(): void {
        $helper = $this->helper(User::class);
        $helper->getProps();
        $helper->getReferences();
        $helper->getPrimaryProp();
        $helper->getTableName();

        $helper->setModelFQN(Post::class);

        self::assertSame(Post::class, $helper->getModelFQN());
        self::assertSame(['id', 'title', 'author'], $helper->getPropNames());
        self::assertSame([], $helper->getReferences());
        self::assertSame('posts', $helper->getTableName());
        self::assertSame('id', $helper->getPrimaryProp()?->getPropName());
        self::assertSame($helper, $helper->setModelFQN(Post::class));
    }

    public function testMissingModel(): void {
        $this->expectException(ModelException::class);
        $this->expectExceptionMessage('Class "App\Missing" does not exist');

        $this->helper('App\Missing')->getProps();
    }

    public function testTableNameOfProperty(): void {
        self::assertNull(new DataStoragePropertyBuilder()->buildTableName(new ReflectionProperty(User::class, 'name')));
        self::assertNull(new DataStoragePropertyBuilder()->buildDataStorageReference(new ReflectionProperty(User::class, 'name')));
        self::assertNull(new DataStoragePropertyBuilder()->buildDataStorageProperty(new ReflectionProperty(User::class, 'notStored')));
    }

    public function testConvertSameTypes(): void {
        $converter = $this->converter();

        self::assertSame('a', $converter->convert('string', 'string', 'a'));
        self::assertSame(1.5, $converter->convert('float', 'float', '1.5'));
        self::assertSame('x', $converter->convert('float', 'float', 'x'));
        self::assertSame('a', $converter->convert('string', 'unknown', 'a'));
    }

    public function testConvertDateTime(): void {
        $converter = $this->converter();

        $dateTime = $converter->convert('int', 'DateTime', '1700000000');
        self::assertInstanceOf(DateTime::class, $dateTime);
        self::assertSame(1700000000, $dateTime->getTimestamp());
        self::assertNull($converter->convert('int', 'DateTime', null));
        self::assertSame(1700000000, $converter->convert('DateTime', 'int', new DateTime('@1700000000')));
        self::assertSame(1700000000, $converter->convert('DateTime', 'int', 1700000000));
        self::assertNull($converter->convert('DateTime', 'int', null));
        self::assertNull($converter->convert('DateTime', 'int', 1.5));
    }

    public function testConvertBoolArrayFloat(): void {
        $converter = $this->converter();

        self::assertTrue($converter->convert('int', 'bool', 1));
        self::assertFalse($converter->convert('int', 'bool', 0));
        self::assertSame(1, $converter->convert('bool', 'int', true));
        self::assertSame(0, $converter->convert('bool', 'int', false));
        self::assertSame(['a' => 'й'], $converter->convert('string', 'array', '{"a":"й"}'));
        self::assertSame('{"a":"й"}', $converter->convert('array', 'string', ['a' => 'й']));
        self::assertSame(2.5, $converter->convert('string', 'float', '2.5'));
        self::assertSame([], $this->logger->records);
    }

    public function testConvertMalformedJson(): void {
        $converter = $this->converter();

        self::assertSame([], $converter->convert('string', 'array', '{broken'));
        self::assertSame([], $converter->convert('string', 'array', null));
        self::assertSame('', $converter->convert('array', 'string', ['bad' => "\xB1"]));
        self::assertCount(3, $this->logger->records);
        self::assertStringStartsWith(
            DataStoragePropertiesConverter::class . ': error while converting from JSON string to array. Reason: ',
            $this->logger->records[0]['message']
        );
        self::assertStringStartsWith(
            DataStoragePropertiesConverter::class . ': error while converting from array to JSON string. Reason: ',
            $this->logger->records[2]['message']
        );
    }

    public function testConvertEntities(): void {
        $driver = new FakeDataStorageDriver();
        ContainerState::withDataStorageDriver($driver);
        $driver->willReturn([['id' => 5, 'name' => 'Ann']]);
        $converter = $this->converter();

        $user = $converter->convert('int', User::class, '5');
        self::assertInstanceOf(User::class, $user);
        self::assertSame('Ann', $user->getName());
        self::assertNull($converter->convert('int', User::class, null));
        self::assertSame(5, $converter->convert(User::class, 'int', $user));
        self::assertSame(7, $converter->convert(User::class, 'int', '7'));
        self::assertNull($converter->convert(User::class, 'int', null));

        $driver->lastInsertId = '9';
        self::assertSame(9, $converter->convert(User::class, 'int', new User()->setName('New')));

        ContainerState::reset();
    }

    public function testEntityThatCannotBeSaved(): void {
        $converter = $this->converter();
        $entity = $this->createStub(User::class);
        $entity->method('isPersistent')->willReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Infinite loop while trying to persist entities.');

        $converter->convert(User::class, 'int', $entity);
    }

    public function testUnknownClassIsNotModel(): void {
        self::assertSame('5', $this->converter()->convert('int', 'App\Missing', '5'));
        self::assertSame(['warning: ' . DataStoragePropertiesConverter::class . '::isModelFQN: class App\Missing does not exist.'], $this->logger->getMessages());
        self::assertSame('5', $this->converter()->convert('int', \stdClass::class, '5'));
    }

    protected function helper(string $modelFQN): DataStoragePropertiesHelper {
        return new DataStoragePropertiesHelper(
            new DataStoragePropertiesProvider(new DataStoragePropertyBuilder())
        )->setModelFQN($modelFQN);
    }

    protected function converter(): DataStoragePropertiesConverter {
        return new DataStoragePropertiesConverter($this->logger, new Converter($this->logger));
    }
}

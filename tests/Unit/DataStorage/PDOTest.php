<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\DataStorage;

use PHPUnit\Framework\TestCase;
use Runway\DataStorage\DTO\DataStorageConnectionOptionsDTO;
use Runway\DataStorage\Event\PDO\PDOConnectionErrorEnvelope;
use Runway\DataStorage\Event\PDO\PDOErrorEnvelope;
use Runway\DataStorage\Event\PDO\PDONotConnectedEnvelope;
use Runway\DataStorage\Event\PDO\PDOStatementErrorEnvelope;
use Runway\DataStorage\Exception\DBNotConnectedException;
use Runway\DataStorage\Exception\PDO\PDOConnectionException;
use Runway\DataStorage\Exception\PDO\PDOStatementException;
use Runway\DataStorage\Exception\PDO\PDOStatementPreparationException;
use Runway\DataStorage\PDO;
use Runway\Tests\Support\EnvStub;
use Runway\Tests\Support\EventDispatcherSpy;

/**
 * The driver on SQLite in memory: the table names in "`{...}`" get the prefix.
 */
class PDOTest extends TestCase {
    protected EventDispatcherSpy $events;

    protected PDO $driver;

    protected function setUp(): void {
        $this->events = new EventDispatcherSpy();
        $this->driver = new PDO(new EnvStub(), $this->events);
        $this->driver->connect(new DataStorageConnectionOptionsDTO('sqlite::memory:', tableNamePrefix: 'app_'));

        $this->driver->execute('CREATE TABLE `{items}` (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, price REAL)');
        $this->driver->execute("INSERT INTO `{items}` (name, price) VALUES ('apple', 1.5), ('pear', 2)");
    }

    public function testConnectionGivesPdo(): void {
        $connection = new PDO(new EnvStub(), $this->events)->connect(new DataStorageConnectionOptionsDTO('sqlite::memory:'));

        self::assertInstanceOf(\PDO::class, $connection);
    }

    public function testResults(): void {
        self::assertSame(
            [['id' => 1, 'name' => 'apple', 'price' => 1.5], ['id' => 2, 'name' => 'pear', 'price' => 2.0]],
            $this->driver->getResult('SELECT * FROM `{items}` ORDER BY id')
        );
        self::assertSame(
            [['name' => 'pear']],
            iterator_to_array($this->driver->getResultsIterator('SELECT name FROM `{items}` WHERE price > ?', 1.5))
        );
        self::assertSame(['id' => 1, 'name' => 'apple', 'price' => 1.5], $this->driver->getFirstResult('SELECT * FROM `{items}`'));
        self::assertSame([], $this->driver->getFirstResult('SELECT * FROM `{items}` WHERE id = :id', ['id' => 5]));
        self::assertSame(['apple', 'pear'], $this->driver->getColumn('SELECT id, name FROM `{items}` ORDER BY id', null, 'name'));
        self::assertSame([1, 2], $this->driver->getColumn('SELECT id, name FROM `{items}` ORDER BY id', [], 0));
        self::assertSame([null, null], $this->driver->getColumn('SELECT id FROM `{items}`', [], 'missing'));
        self::assertSame(2, $this->driver->getFirstScalarResult('SELECT COUNT(*) FROM `{items}`'));
        self::assertNull($this->driver->getFirstScalarResult('SELECT id FROM `{items}` WHERE id = 5'));
        self::assertSame([], iterator_to_array($this->driver->getResultsIterator('SELECT * FROM `{items}` WHERE id = 5')));
    }

    public function testLastInsertId(): void {
        $this->driver->execute('INSERT INTO `{items}` (name) VALUES (:name)', ['name' => 'plum']);

        self::assertSame('3', $this->driver->getLastInsertId());
        self::assertSame('', new PDO(new EnvStub(), $this->events)->getLastInsertId());
    }

    public function testPrefixIsInBackticksOnly(): void {
        self::assertSame(
            [['v' => '{items}', 'n' => 2]],
            $this->driver->getResult("SELECT '{items}' AS v, COUNT(*) AS n FROM `{items}`")
        );
    }

    public function testNotConnected(): void {
        $driver = new PDO(new EnvStub(), $this->events);

        try {
            $driver->getResult('SELECT 1', 5);
            self::fail('No exception');
        } catch (DBNotConnectedException) {
        }

        self::assertCount(1, $this->events->events);
        [$event, $envelope] = $this->events->events[0];
        self::assertSame('db.error.not_connected', $event);
        self::assertInstanceOf(PDONotConnectedEnvelope::class, $envelope);
        self::assertSame('SELECT 1', $envelope->getQuery());
        self::assertSame([5], $envelope->getVars());
    }

    public function testConnectionError(): void {
        try {
            new PDO(new EnvStub(), $this->events)->connect(new DataStorageConnectionOptionsDTO('unknown:driver'));
            self::fail('No exception');
        } catch (PDOConnectionException $e) {
            self::assertSame('unknown:driver', $e->getDsn());
            self::assertInstanceOf(\PDOException::class, $e->getPrevious());
            self::assertSame($e->getPrevious()->getMessage(), $e->getMessage());
        }

        [$event, $envelope] = $this->events->events[0];
        self::assertSame('db.connection.error', $event);
        self::assertInstanceOf(PDOConnectionErrorEnvelope::class, $envelope);
        self::assertSame('unknown:driver', $envelope->getDsn());
    }

    public function testPreparationError(): void {
        try {
            $this->driver->execute('SELECT FROM WHERE `{items}`');
            self::fail('No exception');
        } catch (PDOStatementPreparationException $e) {
            self::assertSame('SELECT FROM WHERE `app_items`', $e->getQuery());
            self::assertStringContainsString('syntax error', $e->getMessage());
            self::assertSame('HY000', $e->getPrevious()?->getCode());
        }

        [$event, $envelope] = $this->events->events[0];
        self::assertSame('db.error.prepare', $event);
        self::assertInstanceOf(PDOErrorEnvelope::class, $envelope);
        self::assertSame('SELECT FROM WHERE `app_items`', $envelope->getQuery());
    }

    public function testStatementError(): void {
        try {
            $this->driver->execute("INSERT INTO `{items}` (name) VALUES ('apple')");
            self::fail('No exception');
        } catch (PDOStatementException $e) {
            self::assertSame('23000', $e->getSqlStateCode());
            self::assertSame(19, $e->getCode());
            self::assertStringContainsString('UNIQUE constraint failed', $e->getMessage());
            self::assertSame("INSERT INTO `app_items` (name) VALUES ('apple')", $e->getStatement()->queryString);
        }

        [$event, $envelope] = $this->events->events[0];
        self::assertSame('db.error', $event);
        self::assertInstanceOf(PDOStatementErrorEnvelope::class, $envelope);
        self::assertSame('23000', $envelope->getSqlStateErrorCode());
        self::assertSame(19, $envelope->getCode());
    }

    public function testConnectOptionsOfEnvironment(): void {
        $options = new PDO(new EnvStub(['DSN' => 'mysql:host=db', 'DB_USER' => 'root', 'DB_PASSWORD' => 12345, 'DB_PREFIX' => 'x_']), $this->events)
            ->getConnectOptions();

        self::assertSame('mysql:host=db', $options->dsn);
        self::assertSame('root', $options->user);
        // A numeric password is an int in the environment.
        self::assertSame('12345', $options->password);
        self::assertSame('x_', $options->tableNamePrefix);

        $defaults = new PDO(new EnvStub(['DB_USER' => ['not', 'scalar']]), $this->events)->getConnectOptions();
        self::assertSame('', $defaults->dsn);
        self::assertNull($defaults->user);
        self::assertNull($defaults->password);
        self::assertSame('', $defaults->tableNamePrefix);
    }
}

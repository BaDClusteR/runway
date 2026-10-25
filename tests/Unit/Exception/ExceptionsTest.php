<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Exception;

use PDO;
use PHPUnit\Framework\TestCase;
use Runway\DataStorage\Event\DBErrorEnvelope;
use Runway\DataStorage\Event\PDO\PDOConnectionErrorEnvelope;
use Runway\DataStorage\Event\PDO\PDOErrorEnvelope;
use Runway\DataStorage\Event\PDO\PDONotConnectedEnvelope;
use Runway\DataStorage\Exception\DBNotConnectedException;
use Runway\DataStorage\Exception\PDO\PDOConnectionException;
use Runway\DataStorage\Exception\PDO\PDOException;
use Runway\DataStorage\QueryBuilder\Exception\ExpressionTypeIsNotAllowed;
use Runway\DataStorage\QueryBuilder\Exception\UnknownJoinConditionTypeException;
use Runway\DataStorage\QueryBuilder\Exception\UnknownJoinTypeException;
use Runway\DataStorage\QueryBuilder\Exception\UnknownOperatorException;
use Runway\Exception\FatalErrorException;
use Runway\FileSystem\Exception\CannotCopyException;
use Runway\Model\Exception\EntityNotFoundException;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\DTO\ServiceDTO;
use Runway\Service\Exception\ConfigurationException;
use Runway\Service\Exception\Route\RouteControllerNotFound;
use Runway\Service\Exception\Route\RouteMethodNotFound;
use RuntimeException;

/**
 * The messages and the data of the exceptions and the error envelopes.
 */
class ExceptionsTest extends TestCase {
    public function testFatalError(): void {
        $previous = new RuntimeException();
        $e = new FatalErrorException(E_USER_ERROR, 'Oops', '/app/a.php', 42, $previous);

        self::assertSame('Fatal error in /app/a.php on line 42: Oops', $e->getMessage());
        self::assertSame(E_USER_ERROR, $e->getCode());
        self::assertSame(E_USER_ERROR, $e->getErrno());
        self::assertSame('Oops', $e->getErrStr());
        self::assertSame('/app/a.php', $e->getErrFile());
        self::assertSame(42, $e->getErrLine());
        self::assertSame($previous, $e->getPrevious());
    }

    public function testConfigurationError(): void {
        self::assertSame('Configuration error in /a.yaml: wrong', new ConfigurationException('/a.yaml', 'wrong')->getMessage());
        self::assertSame('Configuration error: wrong', new ConfigurationException('', 'wrong')->getMessage());
    }

    public function testRouteErrors(): void {
        $route = new RouteDTO('home', '/', 'HomeController', 'index', 0, '', '/routes.yaml');

        self::assertSame(
            'Configuration error in /routes.yaml: Controller HomeController for the route home not found',
            new RouteControllerNotFound($route)->getMessage()
        );
        self::assertSame(
            'Configuration error in /routes.yaml: Method HomeController::index for the route home not found',
            new RouteMethodNotFound($route)->getMessage()
        );
    }

    public function testPdoException(): void {
        $connection = new PDO('sqlite::memory:');
        $e = new PDOException('HY000', "SELECT\n1", $connection, 'syntax error', 7);

        self::assertSame("Error while executing 'SELECT\\n1': syntax error [code HY000]", $e->getMessage());
        self::assertSame("SELECT\n1", $e->getQuery());
        self::assertSame($connection, $e->getConnection());
        self::assertSame(7, $e->getCode());
        self::assertSame('Error while executing unknown SQL query: error [code ]', new PDOException(message: 'error')->getMessage());
    }

    public function testPdoConnectionException(): void {
        $e = new PDOConnectionException('refused', 2, 'mysql:host=db');

        self::assertSame('refused', $e->getMessage());
        self::assertSame('mysql:host=db', $e->getDsn());
        self::assertSame('Not connected to a database', new DBNotConnectedException()->getMessage());
    }

    public function testEnvelopes(): void {
        $connection = new PDO('sqlite::memory:');

        $error = new PDOErrorEnvelope(1, 'syntax error', 'SELECT', $connection, 'HY000');
        self::assertSame("Error while executing 'SELECT'. syntax error", $error->getMessage());
        self::assertSame(1, $error->getCode());
        self::assertSame('SELECT', $error->getQuery());
        self::assertSame('HY000', $error->getSqlStateErrorCode());
        self::assertSame($connection, $error->getConnection());

        $notConnected = new PDONotConnectedEnvelope('SELECT :id', ['id' => 1]);
        self::assertSame('SELECT :id', $notConnected->getQuery());
        self::assertSame(['id' => 1], $notConnected->getVars());
        self::assertNull($notConnected->getConnection());
        self::assertSame("Error while executing 'SELECT :id'. Database not connected", $notConnected->getMessage());

        $connectionError = new PDOConnectionErrorEnvelope(2, 'refused', 'mysql:host=db');
        self::assertSame('mysql:host=db', $connectionError->getDsn());
        self::assertSame('refused', $connectionError->getMessage());

        $dbError = new DBErrorEnvelope(3, 'error', 'SELECT');
        self::assertSame('', $dbError->getSqlStateErrorCode());
    }

    public function testQueryBuilderErrors(): void {
        self::assertSame('Expression type is not allowed here: X', new ExpressionTypeIsNotAllowed('X')->getMessage());
        self::assertSame('Unknown join condition type: USING', new UnknownJoinConditionTypeException('USING')->getMessage());
        self::assertSame('Unknown join type: OUTER', new UnknownJoinTypeException('OUTER')->getMessage());
        self::assertSame('Unknown comparator: ~', new UnknownOperatorException('~')->getMessage());
    }

    public function testOtherErrors(): void {
        self::assertSame('App\User with ID 5 not found in the data storage.', new EntityNotFoundException('App\User', 5)->getMessage());

        $previous = new RuntimeException();
        self::assertSame($previous, new CannotCopyException('/a', '/b', $previous)->getPrevious());
        self::assertSame('a', new ServiceDTO('a')->getName());
    }
}

<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\Exception;

use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\DataStorage\Exception\DBNotConnectedException;
use Runway\DataStorage\Exception\PDO\PDOConnectionException;
use Runway\DataStorage\Exception\PDO\PDOException;
use Runway\DataStorage\Exception\PDO\PDOStatementException;
use Runway\DataStorage\QueryBuilder\Exception\ExpressionTypeIsNotAllowed;
use Runway\DataStorage\QueryBuilder\Exception\UnknownJoinConditionTypeException;
use Runway\DataStorage\QueryBuilder\Exception\UnknownJoinTypeException;
use Runway\DataStorage\QueryBuilder\Exception\UnknownOperatorException;
use Runway\Env\Exception\EnvParserException;
use Runway\Event\DTO\EventDTO;
use Runway\Event\Exception\EmptyEventMethod;
use Runway\Event\Exception\EmptyEventName;
use Runway\Event\Exception\EventException;
use Runway\Event\Exception\EventMethodDoesNotExist;
use Runway\Exception\CircularDependencyException;
use Runway\Model\Exception\EntityNotFoundException;
use Runway\Service\DTO\RouteDTO;
use Runway\Service\DTO\ServiceDTO;
use Runway\Service\Exception\ClassNotFoundException;
use Runway\Service\Exception\ConfigurationException;
use Runway\Service\Exception\Route\RouteControllerNotFound;
use Runway\Service\Exception\Route\RouteDeclaredMoreThanOnce;
use Runway\Service\Exception\Route\RouteException;
use Runway\Service\Exception\Route\RouteMethodNotFound;
use Runway\Service\Exception\ServiceDeclaredMoreThanOnceException;
use Runway\Service\Exception\ServiceDecoratorCallException;
use Runway\Service\Exception\ServiceException;
use Runway\Service\Exception\ServiceNotFoundException;
use Runway\Service\Exception\ServiceUntypedParameterInConstructor;
use Runway\Singleton\DTO\DependencyChainLinkDTO;
use Throwable;

/**
 * The code of an exception is 0 unless it is given.
 */
class ExceptionCodesTest extends TestCase {
    /**
     * @return iterable<string, array{callable(): Throwable}>
     */
    public static function exceptionProvider(): iterable {
        $service = new ServiceDTO('service', 'Service', filePath: '/services.yaml');
        $event = new EventDTO('event', 'service', 'method');
        $route = new RouteDTO('route', '/', 'Controller', 'method', 0, '', '/routes.yaml');

        yield 'DBNotConnectedException' => [static fn(): Throwable => new DBNotConnectedException()];
        yield 'PDOConnectionException' => [static fn(): Throwable => new PDOConnectionException()];
        yield 'PDOException' => [static fn(): Throwable => new PDOException()];
        yield 'PDOStatementException' => [static function (): Throwable {
            $connection = new PDO('sqlite::memory:');
            $statement = $connection->prepare('SELECT 1');

            return new PDOStatementException('HY000', 'SELECT 1', $statement, $connection);
        }];
        yield 'ExpressionTypeIsNotAllowed' => [static fn(): Throwable => new ExpressionTypeIsNotAllowed()];
        yield 'UnknownJoinConditionTypeException' => [static fn(): Throwable => new UnknownJoinConditionTypeException('X')];
        yield 'UnknownJoinTypeException' => [static fn(): Throwable => new UnknownJoinTypeException('X')];
        yield 'UnknownOperatorException' => [static fn(): Throwable => new UnknownOperatorException('X')];
        yield 'EnvParserException' => [static fn(): Throwable => new EnvParserException('')];
        yield 'EmptyEventMethod' => [static fn(): Throwable => new EmptyEventMethod($service, $event)];
        yield 'EmptyEventName' => [static fn(): Throwable => new EmptyEventName($service, $event)];
        yield 'EventException' => [static fn(): Throwable => new EventException($service, $event)];
        yield 'EventMethodDoesNotExist' => [static fn(): Throwable => new EventMethodDoesNotExist($service, $event)];
        yield 'CircularDependencyException' => [static fn(): Throwable => new CircularDependencyException([])];
        yield 'EntityNotFoundException' => [static fn(): Throwable => new EntityNotFoundException('Model', 1)];
        yield 'ClassNotFoundException' => [static fn(): Throwable => new ClassNotFoundException('Class', $service)];
        yield 'ConfigurationException' => [static fn(): Throwable => new ConfigurationException('/a.yaml')];
        yield 'RouteControllerNotFound' => [static fn(): Throwable => new RouteControllerNotFound($route)];
        yield 'RouteDeclaredMoreThanOnce' => [static fn(): Throwable => new RouteDeclaredMoreThanOnce($route)];
        yield 'RouteException' => [static fn(): Throwable => new RouteException($route, 'message')];
        yield 'RouteMethodNotFound' => [static fn(): Throwable => new RouteMethodNotFound($route)];
        yield 'ServiceDeclaredMoreThanOnceException' => [static fn(): Throwable => new ServiceDeclaredMoreThanOnceException($service, $service)];
        yield 'ServiceDecoratorCallException' => [static fn(): Throwable => new ServiceDecoratorCallException($service)];
        yield 'ServiceException' => [static fn(): Throwable => new ServiceException($service, 'message')];
        yield 'ServiceNotFoundException' => [static fn(): Throwable => new ServiceNotFoundException($service)];
        yield 'ServiceUntypedParameterInConstructor' => [static fn(): Throwable => new ServiceUntypedParameterInConstructor($service, 'parameter')];
    }

    /**
     * @param callable(): Throwable $create
     */
    #[DataProvider('exceptionProvider')]
    public function testCodeIsZeroByDefault(callable $create): void {
        $exception = $create();

        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    public function testCircularDependencyMessage(): void {
        $exception = new CircularDependencyException([
            new DependencyChainLinkDTO('a', '/a.php', 1, 'A::get'),
            new DependencyChainLinkDTO('b', '/b.php', 2, 'B::get'),
        ]);

        self::assertSame(
            "Circular dependency detected:\n1: a in /a.php, line 1: A::get\n2: b in /b.php, line 2: B::get\n",
            $exception->getMessageText()
        );
        self::assertSame($exception->getMessageText(), $exception->getMessage());
    }
}

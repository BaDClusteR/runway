<?php

declare(strict_types=1);

namespace Runway\Tests\Unit\DataStorage;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Runway\DataStorage\QueryBuilder\Converter\ComparisonOperatorConverter;
use Runway\DataStorage\QueryBuilder\Converter\JoinConditionTypeConverter;
use Runway\DataStorage\QueryBuilder\Converter\JoinTypeConverter;
use Runway\DataStorage\QueryBuilder\Converter\TableNameEscaper;
use Runway\DataStorage\QueryBuilder\DTO\ExpressionOrderPartDTO;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionComparisonOperatorsEnum as Operator;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinConditionTypeEnum;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionJoinTypeEnum;
use Runway\DataStorage\QueryBuilder\Enum\ExpressionOrderDirectionEnum;
use Runway\DataStorage\QueryBuilder\Exception\ExpressionTypeIsNotAllowed;
use Runway\DataStorage\QueryBuilder\Exception\UnknownJoinConditionTypeException;
use Runway\DataStorage\QueryBuilder\Exception\UnknownJoinTypeException;
use Runway\DataStorage\QueryBuilder\Exception\UnknownOperatorException;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionAnd;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionComparison;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionFrom;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionGroupBy;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionMath;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionOr;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionSelect;
use Runway\DataStorage\QueryBuilder\Expression\ExpressionSet;
use Runway\DataStorage\QueryBuilder\ExpressionBuilder\ExpressionBuilder;

class ExpressionsTest extends TestCase {
    protected ExpressionBuilder $expr;

    protected function setUp(): void {
        $this->expr = new ExpressionBuilder();
    }

    public function testComparisons(): void {
        self::assertSame('a = :a', (string)$this->expr->eq('a', ':a'));
        self::assertSame('a <> 1', (string)$this->expr->neq('a', 1));
        self::assertSame('a > 1', (string)$this->expr->gt('a', 1));
        self::assertSame('a < 1', (string)$this->expr->lt('a', 1));
        self::assertSame('a >= 1', (string)$this->expr->ge('a', 1));
        self::assertSame('a <= 1', (string)$this->expr->le('a', 1));
        self::assertSame('a IN (:a_0, :a_1)', (string)$this->expr->in('a', [':a_0', ':a_1']));
        self::assertSame('a IN (SELECT id FROM b)', (string)$this->expr->in('a', 'SELECT id FROM b'));
        self::assertSame('a LIKE :pattern', (string)new ExpressionComparison('a', Operator::LIKE, ':pattern'));
        self::assertSame('a NOT LIKE :pattern', (string)new ExpressionComparison('a', Operator::NOT_LIKE, ':pattern'));
    }

    public function testNullChecks(): void {
        self::assertSame('(a IS NULL)', (string)$this->expr->isNull('a'));
        self::assertSame('(a IS NOT NULL)', (string)$this->expr->isNotNull('a'));
    }

    public function testFunctionsAndMath(): void {
        self::assertSame('COALESCE(a, 1, NOW())', (string)$this->expr->func('COALESCE', 'a', 1, $this->expr->func('NOW')));
        self::assertSame('COUNT()', (string)$this->expr->func('COUNT', ['not', 'scalar']));
        self::assertSame('(a + (b * 2))', (string)new ExpressionMath('a', '+', new ExpressionMath('b', '*', '2')));
        self::assertSame('MAX(a) = 1', (string)$this->expr->eq($this->expr->func('MAX', 'a'), 1));
    }

    public function testBooleans(): void {
        self::assertSame(
            '(a = 1 AND (b = 2 OR c = 3 OR (d IS NOT NULL)))',
            (string)$this->expr->and(
                $this->expr->eq('a', 1),
                $this->expr->or($this->expr->eq('b', 2), 'c = 3', $this->expr->isNotNull('d'))
            )
        );
        self::assertSame('(a AND b)', (string)new ExpressionAnd('a')->add('b'));
        self::assertSame('(a OR (x + 1))', (string)new ExpressionOr('a')->add(new ExpressionMath('x', '+', '1')));
        self::assertSame('', (string)$this->expr->and());
        self::assertSame(['a', 'b'], new ExpressionAnd('a', 'b')->getParts());
        self::assertSame('', (string)new ExpressionAnd('a')->reset());
    }

    public function testNotAllowedPart(): void {
        $this->expectException(ExpressionTypeIsNotAllowed::class);
        $this->expectExceptionMessage('Expression type is not allowed here: ' . ExpressionFrom::class);

        $this->expr->and(new ExpressionFrom('users'));
    }

    public function testNotAllowedAddedPart(): void {
        $this->expectException(ExpressionTypeIsNotAllowed::class);

        new ExpressionOr('a')->add(new ExpressionSet());
    }

    public function testOtherComposites(): void {
        self::assertSame('(a, b)', (string)new ExpressionSelect('a', 'b'));
        self::assertSame('GROUP BY a, b', (string)new ExpressionGroupBy('a')->add('b'));
        self::assertSame('SET `a` = 1, `b` = :b', (string)new ExpressionSet()->add('a', 1)->add('b', ':b'));
        self::assertSame('', (string)new ExpressionSet());
    }

    public function testFrom(): void {
        self::assertSame('FROM `{users}` u INDEX BY u.id', (string)new ExpressionFrom('users', 'u', 'u.id'));
        self::assertSame('FROM `{users}`', (string)new ExpressionFrom('users'));
        self::assertSame('FROM ', (string)new ExpressionFrom(''));
    }

    public function testOrderPart(): void {
        $part = new ExpressionOrderPartDTO('a', ExpressionOrderDirectionEnum::DESC);

        self::assertSame('a', $part->getField());
        self::assertSame('DESC', $part->getDirection());
        self::assertSame('ASC', new ExpressionOrderPartDTO('a', ExpressionOrderDirectionEnum::ASC)->getDirection());
    }

    public function testTableNameEscaper(): void {
        self::assertSame('`{users}`', new TableNameEscaper()->escapeTableName('users'));
        self::assertSame('', new TableNameEscaper()->escapeTableName(''));
    }

    /**
     * @return iterable<array{string, Operator, string}>
     */
    public static function operatorProvider(): iterable {
        yield ['>=', Operator::GREATER_OR_EQUAL, '>='];
        yield ['<=', Operator::LESS_OR_EQUAL, '<='];
        yield ['<', Operator::LESS_THAN, '<'];
        yield ['>', Operator::GREATER_THAN, '>'];
        yield ['=', Operator::EQUAL, '='];
        yield ['<>', Operator::NOT_EQUAL, '<>'];
        yield ['!=', Operator::NOT_EQUAL, '<>'];
        yield ['like', Operator::LIKE, 'LIKE'];
        yield ['IS LIKE', Operator::LIKE, 'LIKE'];
        yield ['not like', Operator::NOT_LIKE, 'NOT LIKE'];
        yield ['IS NOT LIKE', Operator::NOT_LIKE, 'NOT LIKE'];
        yield ['in', Operator::IN, 'IN'];
    }

    #[DataProvider('operatorProvider')]
    public function testOperators(string $string, Operator $operator, string $sql): void {
        $converter = new ComparisonOperatorConverter();

        self::assertSame($operator, $converter->fromString($string));
        self::assertSame($sql, $converter->toString($operator));
    }

    public function testUnknownOperator(): void {
        $this->expectException(UnknownOperatorException::class);
        $this->expectExceptionMessage('Unknown comparator: ~');

        new ComparisonOperatorConverter()->fromString('~');
    }

    public function testJoinTypes(): void {
        $converter = new JoinTypeConverter();

        foreach (
            [
                'INNER' => ExpressionJoinTypeEnum::INNER_JOIN,
                'LEFT'  => ExpressionJoinTypeEnum::LEFT_JOIN,
                'RIGHT' => ExpressionJoinTypeEnum::RIGHT_JOIN,
                'CROSS' => ExpressionJoinTypeEnum::CROSS_JOIN,
                'SELF'  => ExpressionJoinTypeEnum::SELF_JOIN,
            ] as $string => $type
        ) {
            self::assertSame($string, $converter->toString($type));
            self::assertSame($type, $converter->fromString($string));
            self::assertSame($type, $converter->fromString(' ' . strtolower($string) . ' join '));
        }
    }

    public function testUnknownJoinType(): void {
        $this->expectException(UnknownJoinTypeException::class);
        $this->expectExceptionMessage('Unknown join type: OUTER');

        new JoinTypeConverter()->fromString('outer join');
    }

    public function testJoinConditionTypes(): void {
        $converter = new JoinConditionTypeConverter();

        self::assertSame('ON', $converter->toString(ExpressionJoinConditionTypeEnum::ON));
        self::assertSame('WITH', $converter->toString(ExpressionJoinConditionTypeEnum::WITH));
        self::assertSame(ExpressionJoinConditionTypeEnum::ON, $converter->fromString(' on '));
        self::assertSame(ExpressionJoinConditionTypeEnum::WITH, $converter->fromString('With'));
    }

    public function testUnknownJoinConditionType(): void {
        $this->expectException(UnknownJoinConditionTypeException::class);
        $this->expectExceptionMessage('Unknown join condition type: using');

        new JoinConditionTypeConverter()->fromString('using');
    }
}

<?php declare(strict_types=1);

namespace Lmc\ApiFilter\Service;

use Lmc\ApiFilter\AbstractTestCase;
use Lmc\ApiFilter\Entity\Value;
use Lmc\ApiFilter\Exception\InvalidArgumentException;
use Lmc\ApiFilter\Filter\FilterIn;
use Lmc\ApiFilter\Filter\FilterWithOperator;
use Lmc\ApiFilter\Filters\Filters;

/**
 * @covers \Lmc\ApiFilter\Exception\TupleException
 * @covers \Lmc\ApiFilter\Service\Parser\AbstractParser
 * @covers \Lmc\ApiFilter\Service\QueryParametersParser
 */
class QueryParametersParserTest extends AbstractTestCase
{
    /** @var QueryParametersParser */
    private $queryParametersParser;

    protected function setUp(): void
    {
        $this->queryParametersParser = new QueryParametersParser(
            new FilterFactory()
        );
    }

    /**
     * @test
     * @dataProvider provideQueryParameters
     */
    public function shouldParseQueryParameters(array $queryParameters, array $expectedFilters): void
    {
        $expectedFilters = Filters::from($expectedFilters);

        $result = $this->queryParametersParser->parse($queryParameters);

        $this->assertEquals($expectedFilters, $result);
    }

    public function provideQueryParameters(): array
    {
        return [
            // queryParameters, expectedFilters
            'empty' => [[], []],
            'simple - implicit eq' => [
                ['title' => 'foo'],
                [new FilterWithOperator('title', new Value('foo'), '=', 'eq')],
            ],
            'two cols - implicit eq' => [
                ['title' => 'foo', 'value' => 'bar'],
                [
                    new FilterWithOperator('title', new Value('foo'), '=', 'eq'),
                    new FilterWithOperator('value', new Value('bar'), '=', 'eq'),
                ],
            ],
            'implicit EQ + explicit filter' => [
                ['name' => 'Jon', 'age' => ['gt' => 20]],
                [
                    new FilterWithOperator('name', new Value('Jon'), '=', 'eq'),
                    new FilterWithOperator('age', new Value(20), '>', 'gt'),
                ],
            ],
            'explicit eq' => [
                ['title' => ['eq' => 'foo']],
                [new FilterWithOperator('title', new Value('foo'), '=', 'eq')],
            ],
            'one col more filters - explicit' => [
                ['title' => ['eq' => 'foo', 'gt' => 'abc']],
                [
                    new FilterWithOperator('title', new Value('foo'), '=', 'eq'),
                    new FilterWithOperator('title', new Value('abc'), '>', 'gt'),
                ],
            ],
            'one col more filters + other col - explicit/implicit' => [
                ['title' => ['gt' => '0', 'lt' => '10'], 'value' => 'foo'],
                [
                    new FilterWithOperator('title', new Value('0'), '>', 'gt'),
                    new FilterWithOperator('title', new Value('10'), '<', 'lt'),
                    new FilterWithOperator('value', new Value('foo'), '=', 'eq'),
                ],
            ],
            'one col - between - explicit' => [
                ['title' => ['gte' => '0', 'lte' => '10']],
                [
                    new FilterWithOperator('title', new Value('0'), '>=', 'gte'),
                    new FilterWithOperator('title', new Value('10'), '<=', 'lte'),
                ],
            ],
            'explicit in' => [
                ['color' => ['in' => ['red', 'green', 'blue']]],
                [
                    new FilterIn('color', new Value(['red', 'green', 'blue'])),
                ],
            ],
            'implicit eq + explicit in' => [
                ['allowed' => true, 'id' => ['in' => [1, 2, 3]]],
                [
                    new FilterWithOperator('allowed', new Value(true), '=', 'eq'),
                    new FilterIn('id', new Value([1, 2, 3])),
                ],
            ],
            'tuple - implicit eq + explicit in' => [
                ['(zone,bucket)' => '(lmc,all)', 'id' => ['in' => [1, 2, 3]]],
                [
                    new FilterWithOperator('zone', new Value('lmc'), '=', 'eq'),
                    new FilterWithOperator('bucket', new Value('all'), '=', 'eq'),
                    new FilterIn('id', new Value([1, 2, 3])),
                ],
            ],
            'tuple - between - explicit in values' => [
                ['(number,alpha)' => ['gte' => '(0, a)', 'lt' => '(10, z)']],
                [
                    new FilterWithOperator('number', new Value('0'), '>=', 'gte'),
                    new FilterWithOperator('alpha', new Value('a'), '>=', 'gte'),
                    new FilterWithOperator('number', new Value('10'), '<', 'lt'),
                    new FilterWithOperator('alpha', new Value('z'), '<', 'lt'),
                ],
            ],
            'ints - between - explicit' => [
                ['age' => ['gt' => 18, 'lt' => 30]],
                [
                    new FilterWithOperator('age', new Value(18), '>', 'gt'),
                    new FilterWithOperator('age', new Value(30), '<', 'lt'),
                ],
            ],
            'explicit between + explicit in' => [
                ['age' => ['gt' => 18, 'lt' => 30], 'size' => ['in' => ['DD', 'D']]],
                [
                    new FilterWithOperator('age', new Value(18), '>', 'gt'),
                    new FilterWithOperator('age', new Value(30), '<', 'lt'),
                    new FilterIn('size', new Value(['DD', 'D'])),
                ],
            ],
        ];
    }

    /**
     * @test
     * @dataProvider provideInvalidQueryParameters
     */
    public function shouldThrowInvalidArgumentExceptionOnUnknownFilter(
        array $queryParameters,
        string $expectedMessage
    ): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->queryParametersParser->parse($queryParameters);
    }

    public function provideInvalidQueryParameters(): array
    {
        return [
            // queryParameters, expected message
            'empty filter' => [
                ['column' => ['' => 'value']],
                'Filter "" is not implemented. For column "column" with value "value".',
            ],
            'unknown filter' => [
                ['column' => ['unknown' => 'value']],
                'Filter "unknown" is not implemented. For column "column" with value "value".',
            ],
            'tuple columns and a single value' => [
                ['(col1, col2)' => 'value'],
                'Invalid combination of a tuple and a scalar. Column (col1, col2) and value value.',
            ],
            'more columns than values' => [
                ['(col1, col2, col3)' => '(val1, val2)'],
                'Number of given columns (3) and values (2) in tuple are not same.',
            ],
            'more values than columns' => [
                ['(col1, col2)' => '(val1, val2, val3)'],
                'Number of given columns (2) and values (3) in tuple are not same.',
            ],
            'invalid tuple - explicit filters' => [
                ['(id,name)' => ['eq' => '(42,foo,bar)']],
                'Number of given columns (2) and values (3) in tuple are not same.',
            ],
            'tuples in IN filter' => [
                ['(id, name)' => ['in' => ['(1,one)', '(2,two)']]],
                'Tuples are not allowed in IN filter.',
            ],
            'invalid tuple' => [
                ['(id, name)' => '(foo)'],
                'Tuple must have at least two values.',
            ],
        ];
    }
}

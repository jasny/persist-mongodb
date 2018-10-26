<?php

namespace Jasny\DB\Mongo\Tests\QueryBuilder;

use Improved\Iterator\CombineIterator;
use Jasny\DB\Mongo\QueryBuilder\BuildStep;
use Jasny\DB\Mongo\QueryBuilder\OptionConverter;
use Jasny\DB\Mongo\QueryBuilder\Query;
use Jasny\DB\Option\QueryOptionInterface;
use Jasny\TestHelper;
use PHPUnit\Framework\MockObject\Builder\InvocationMocker;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Jasny\DB\Mongo\QueryBuilder\BuildStep
 */
class BuildStepTest extends TestCase
{
    use TestHelper;

    public function test()
    {
        $option = $this->createMock(QueryOptionInterface::class);
        $optionConverter = $this->createMock(OptionConverter::class);
        $optionConverter->expects($this->once())->method('convert')
            ->with([$option])->willReturn(['limit' => 10]);

        $callbacks = [];

        $callbacks[] = $this->createCallbackMock($this->once(), function(InvocationMocker $invoke) use ($option) {
            $invoke->with($this->isInstanceOf(Query::class), 'foo', '', 42, [$option]);
            $invoke->willReturnCallback(function($query) {
                $query->add(['foo' => 'XLII']);
            });
        });

        $callbacks[] = $this->createCallbackMock($this->once(), function(InvocationMocker $invoke) use ($option) {
            $invoke->with($this->isInstanceOf(Query::class), 'color', 'not', 'blue', [$option]);
            $invoke->willReturnCallback(function($query) {
                $query->add(['color' => ['$not' => 'blue']]);
            });
        });

        $info = [
            ['field' => 'foo', 'operator' => '', 'value' => 42],
            ['field' => 'color', 'operator' => 'not', 'value' => 'blue']
        ];

        $build = new BuildStep($optionConverter);
        $query = $build(new CombineIterator($info, $callbacks), [$option]);

        $this->assertInstanceOf(Query::class, $query);
        $this->assertEquals(['foo' => 'XLII', 'color' => ['$not' => 'blue']], $query->toArray());
    }
}
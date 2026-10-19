<?php

namespace SGW_Import\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use SGW_Import\Import\Column;

class ColumnTest extends TestCase
{
    public function testCreateByArrayKeepsTheOrderAndMarksTheHiddenColumns(): void
    {
        $columns = Column::createByArray(['Date', 'Amount', 'Payee', 'Balance'], ['Balance', 'Amount']);

        $this->assertSame(['Date', 'Amount', 'Payee', 'Balance'], array_map(function (Column $c) {
            return $c->name;
        }, $columns));
        $this->assertSame([false, true, false, true], array_map(function (Column $c) {
            return $c->hidden;
        }, $columns));
    }

    public function testANewColumnIsShown(): void
    {
        $this->assertFalse((new Column('Date'))->hidden);
    }
}

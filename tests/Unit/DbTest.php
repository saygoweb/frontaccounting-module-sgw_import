<?php

namespace SGW_Import\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SGW\DB;

class DbTest extends TestCase
{
    protected function tearDown(): void
    {
        DB::init('');
    }

    public function testPrefixQuotesTheTableWithTheCompanysPrefix(): void
    {
        DB::init('3_');

        $this->assertSame('3_', DB::tablePrefix());
        $this->assertSame('`3_bank_trans`', DB::prefix('bank_trans'));
    }
}

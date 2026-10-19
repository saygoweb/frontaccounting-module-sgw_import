<?php

namespace SGW_Import\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use SGW_Import\Import\Importers\ImportState;

class ImportStateTest extends TestCase
{
    public function testADocumentIsUsedOnceItIsPushedForThatPartyAndAmount(): void
    {
        $state = new ImportState();
        $this->assertFalse($state->isUsed('2026-01-05', 'ACME', '-10.00', 7));

        $state->pushPartyAmount('2026-01-05', 'ACME', '-10.00', 7);

        $this->assertTrue($state->isUsed('2026-01-05', 'ACME', '-10.00', 7));
        $this->assertFalse($state->isUsed('2026-01-05', 'ACME', '-10.00', 8), 'another document');
        $this->assertFalse($state->isUsed('2026-01-05', 'ACME', '-11.00', 7), 'another amount');
        $this->assertFalse($state->isUsed('2026-01-05', 'Other', '-10.00', 7), 'another party');
    }

    public function testAnotherDateStartsAfresh(): void
    {
        $state = new ImportState();
        $state->pushPartyAmount('2026-01-05', 'ACME', '-10.00', 7);

        $state->pushPartyAmount('2026-01-06', 'ACME', '-10.00', 8);

        $this->assertFalse($state->isUsed('2026-01-06', 'ACME', '-10.00', 7));
        $this->assertTrue($state->isUsed('2026-01-06', 'ACME', '-10.00', 8));
    }

    public function testPushingADocumentTwiceIsRefused(): void
    {
        $state = new ImportState();
        $state->pushPartyAmount('2026-01-05', 'ACME', '-10.00', 7);

        $this->expectExceptionMessage("Pushing document that is already pushed: '7'");
        $state->pushPartyAmount('2026-01-05', 'ACME', '-10.00', 7);
    }
}

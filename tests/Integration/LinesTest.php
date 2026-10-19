<?php

namespace SGW_Import\Tests\Integration;

use SGW_Import\Import\Lines;
use SGW_Import\Model\ImportLineModel;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LinesTest extends FaTestCase
{
    public function testARowMatchesTheLineForItsPayeeWhateverItsCase(): void
    {
        $this->fileType();
        $supplier = $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius');
        $phone = $this->line(ImportLineModel::PT_QUICK_PAYMENT, 2, 'PHONE CO');

        $lines = new Lines(self::BANK);

        $matched = $lines->matchingLine($this->row('2026-01-05', '-1', 'dino saurius'));
        $this->assertSame((string) $supplier->id, (string) $matched->id);
        $matched = $lines->matchingLine($this->row('2026-01-05', '-1', 'Phone Co'));
        $this->assertSame((string) $phone->id, (string) $matched->id);
        $this->assertNull($lines->matchingLine($this->row('2026-01-05', '-1', 'Someone else')));
        $partyLineId = $lines->partyLineId($this->row('2026-01-05', '-1', 'DINO SAURIUS'));
        $this->assertSame((string) $supplier->id, (string) $partyLineId);
        $this->assertNull($lines->partyLineId($this->row('2026-01-05', '-1', 'Someone else')));
    }

    public function testABankAccountWithoutLinesMatchesNothing(): void
    {
        $this->fileType();

        $this->assertNull((new Lines(self::BANK))->matchingLine($this->row('2026-01-05', '-1', 'Dino Saurius')));
    }
}

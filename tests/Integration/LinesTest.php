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

    public function testARowMatchesTheLongestLineFoundWithinItsPayee(): void
    {
        $this->fileType();
        $google = $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Google');
        $cloud = $this->line(ImportLineModel::PT_SUPPLIER, 2, 'Google CLOUD');
        $exact = $this->line(ImportLineModel::PT_SUPPLIER, 3, 'Google Cloud VL8RQC');

        $lines = new Lines(self::BANK);

        $matched = $lines->matchingLine($this->row('2026-01-05', '-1', 'GOOGLE CLOUD AB12CD'));
        $this->assertSame((string) $cloud->id, (string) $matched->id);
        $matched = $lines->matchingLine($this->row('2026-01-05', '-1', 'Google Workspace'));
        $this->assertSame((string) $google->id, (string) $matched->id);
        $matched = $lines->matchingLine($this->row('2026-01-05', '-1', 'google cloud vl8rqc'));
        $this->assertSame((string) $exact->id, (string) $matched->id);
        $partyLineId = $lines->partyLineId($this->row('2026-01-05', '-1', 'Google CLOUD X'));
        $this->assertSame((string) $cloud->id, (string) $partyLineId);
        $this->assertNull($lines->matchingLine($this->row('2026-01-05', '-1', 'Goog')));
    }

    public function testALineMatchesOnlyInItsOwnColumn(): void
    {
        $this->fileType();
        $memo = $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Rent', ['partyField' => 'Memo']);

        $lines = new Lines(self::BANK);

        $this->assertNull($lines->matchingLine($this->row('2026-01-05', '-1', 'Rent', 'Other')));
        $this->assertNull($lines->matchingLine($this->row('2026-01-05', '-1', 'Rent Co', 'Other')));
        $matched = $lines->matchingLine($this->row('2026-01-05', '-1', 'Landlord', 'June rent'));
        $this->assertSame((string) $memo->id, (string) $matched->id);
    }

    public function testABankAccountWithoutLinesMatchesNothing(): void
    {
        $this->fileType();

        $this->assertNull((new Lines(self::BANK))->matchingLine($this->row('2026-01-05', '-1', 'Dino Saurius')));
    }
}

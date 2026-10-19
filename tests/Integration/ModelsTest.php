<?php

namespace SGW_Import\Tests\Integration;

use SGW_Import\Model\ImportFileListModel;
use SGW_Import\Model\ImportFileModel;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Model\PartyCodeModel;

/**
 * sgw_import's models against its tables (data/0.1.0.sql) and FrontAccounting's.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ModelsTest extends FaTestCase
{
    public function testAFileTypeKeepsItsColumnListsThroughTheTable(): void
    {
        $this->fileType(self::PETTY_CASH, ImportFileTypeModel::DTF_DDMMYY);

        $read = ImportFileTypeModel::findByBankId(self::PETTY_CASH);

        $this->assertSame(['Date', 'Amount', 'Payee', 'Memo'], $read->columns);
        $this->assertSame(['Memo'], $read->hide);
        $this->assertSame('Date', $read->dateField);
        $this->assertSame('Amount', $read->amountField);
        $this->assertSame(ImportFileTypeModel::DTF_DDMMYY, $read->dateFormat);
        $this->assertSame(3, $read->columnKey('Memo'));
        $this->assertNotFalse(ImportFileTypeModel::findOne());

        $this->assertTrue(ImportFileTypeModel::deleteById($read->id));
        $this->assertFalse(ImportFileTypeModel::findByBankId(self::PETTY_CASH));
    }

    public function testLinesAreFoundByTheirBankAccount(): void
    {
        $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius');
        $this->line(ImportLineModel::PT_QUICK_PAYMENT, 2, 'Phone Co');

        $matches = [];
        foreach (ImportLineModel::findByBankId(self::BANK) as $line) {
            $matches[$line->partyMatch] = $line->partyType;
        }
        $this->assertSame(
            ['Dino Saurius' => ImportLineModel::PT_SUPPLIER, 'Phone Co' => ImportLineModel::PT_QUICK_PAYMENT],
            $matches
        );
        $this->assertSame([], $this->all(ImportLineModel::findByBankId(self::PETTY_CASH)));
    }

    public function testALineIsDeletedById(): void
    {
        $line = $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius');

        $this->assertTrue(ImportLineModel::deleteById($line->id));

        $this->assertSame([], $this->all(ImportLineModel::findByBankId(self::BANK)));
    }

    public function testFixDefaultsFillsAnUnsetPartyAndDocumentType(): void
    {
        $line = new ImportLineModel();
        // As read from a row whose party_type and doc_type are NULL.
        $line->partyType = null; // @phpstan-ignore-line
        $line->docType = null; // @phpstan-ignore-line

        $line->fixDefaults();

        $this->assertSame(ImportLineModel::PT_SUPPLIER, $line->partyType);
        $this->assertSame(ImportLineModel::DT_NONE, $line->docType);
    }

    public function testAFileIsFoundByNameAndListedWithItsBankAccount(): void
    {
        $file = new ImportFileModel();
        $file->bankId = self::PETTY_CASH;
        $file->fileName = 'statement-models-test.csv';
        $file->dtImport = $this->date() . ' 10:00:00';
        $file->write();

        $found = ImportFileModel::findByFileName('statement-models-test.csv');
        $this->assertSame((string) $file->id, (string) $found->id);
        $listed = [];
        foreach (ImportFileListModel::find() as $item) {
            $listed[$item->fileName] = $item->bankAccountName;
        }
        $this->assertSame(['statement-models-test.csv' => 'Petty Cash account'], $listed);

        $this->assertTrue(ImportFileModel::deleteById($file->id));
        $this->assertFalse(ImportFileModel::findByFileName('statement-models-test.csv'));
    }

    public function testPartyCodesComeFromFrontAccountingsOwnTables(): void
    {
        $this->assertSame('Donald Easter', PartyCodeModel::findByCustomer(1)->code);
        $this->assertSame('Dino Saurius', PartyCodeModel::findBySupplier(1)->code);
        $this->assertSame('Phone', PartyCodeModel::findByQuick(2)->code);
        $this->assertSame('Petty Cash account', PartyCodeModel::findByBank(self::PETTY_CASH)->code);
    }

    public function testAPartyThatIsNotThereIsRefused(): void
    {
        $this->expectException(\Exception::class);
        PartyCodeModel::findBySupplier(999);
    }

    /**
     * @param iterable<ImportLineModel>|bool $found
     * @return ImportLineModel[]
     */
    private function all($found): array
    {
        $this->assertIsIterable($found);
        $all = [];
        foreach ($found as $model) {
            $all[] = $model;
        }

        return $all;
    }
}

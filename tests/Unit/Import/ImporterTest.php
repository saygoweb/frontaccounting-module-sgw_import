<?php

namespace SGW_Import\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use SGW_Import\Import\Importers\CustomerImporter;
use SGW_Import\Import\Importers\Importer;
use SGW_Import\Import\Importers\ImportState;
use SGW_Import\Import\Importers\QuickDepositImporter;
use SGW_Import\Import\Importers\QuickPaymentImporter;
use SGW_Import\Import\Importers\SupplierImporter;
use SGW_Import\Import\Importers\TransferImporter;
use SGW_Import\Import\Row;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Tests\Support\Models;

class ImporterTest extends TestCase
{
    /**
     * @return array<string, array{string, class-string<Importer>}>
     */
    public function partyTypes(): array
    {
        return [
            'customer' => [ImportLineModel::PT_CUSTOMER, CustomerImporter::class],
            'supplier' => [ImportLineModel::PT_SUPPLIER, SupplierImporter::class],
            'quick deposit' => [ImportLineModel::PT_QUICK_DEPOSIT, QuickDepositImporter::class],
            'quick payment' => [ImportLineModel::PT_QUICK_PAYMENT, QuickPaymentImporter::class],
            'transfer' => [ImportLineModel::PT_TRANSFER, TransferImporter::class],
        ];
    }

    /**
     * @dataProvider partyTypes
     * @param class-string<Importer> $class
     */
    public function testFromPartyTypeMakesThatPartysImporter(string $partyType, string $class): void
    {
        $this->assertInstanceOf($class, Importer::fromPartyType($partyType, Models::fileType(), new ImportState()));
    }

    public function testAnUnknownPartyTypeIsRefused(): void
    {
        $this->expectExceptionMessage("Could not create Importer for 'nobody'");
        Importer::fromPartyType('nobody', Models::fileType(), new ImportState());
    }

    public function testAColumnTheFileTypeLacksIsRefused(): void
    {
        $fileType = Models::fileType(['columns' => ['Date', 'Payee']]);

        $this->expectExceptionMessage("Column 'Amount' not found");
        Importer::fromPartyType(ImportLineModel::PT_SUPPLIER, $fileType, new ImportState());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function bnzDates(): array
    {
        $forms = [];
        foreach ([ImportFileTypeModel::DTF_YYYYMMDD, ImportFileTypeModel::DTF_DDMMYY] as $format) {
            $forms["$format dd/mm/yy"] = [$format, '04/03/26'];
            $forms["$format dd/mm/yyyy"] = [$format, '04/03/2026'];
            $forms["$format yyyy-mm-dd"] = [$format, '2026-03-04'];
        }
        return $forms;
    }

    /**
     * BNZ's exports change form from one download to the next: 2026-07-07,
     * 06/08/2026 and 08/09/26 came three months running. Each is read, under
     * either file type, as the day it names.
     *
     * @dataProvider bnzDates
     */
    public function testSqlDateReadsEachFormBnzExports(string $format, string $date): void
    {
        $this->assertSame('2026-03-04', $this->importer($format)->sqlDate($date));
    }

    /**
     * @return array<string, array{string}>
     */
    public function notADate(): array
    {
        return [
            'one-digit day and month' => ['4/3/26'],
            'one-digit month and day' => ['2026-3-4'],
            'no 31 September (yy)' => ['31/09/26'],
            'no 31 September (yyyy)' => ['31/09/2026'],
            'no 30 February' => ['2026-02-30'],
            'dashes, day first' => ['04-03-26'],
            'three-digit year' => ['04/03/202'],
            'empty' => [''],
        ];
    }

    /**
     * MySQL stores a malformed date as 0000-00-00 or reads it as another day,
     * so anything else is refused.
     *
     * @dataProvider notADate
     */
    public function testSqlDateRefusesWhatIsNotADate(string $date): void
    {
        $importer = $this->importer(ImportFileTypeModel::DTF_DDMMYY);

        $this->expectExceptionMessage("Bad date '$date'");
        $importer->sqlDate($date);
    }

    public function testSqlDateRefusesAnUnknownFormat(): void
    {
        $importer = $this->importer('mmddyy');

        $this->expectExceptionMessage("Unsupported date format 'mmddyy'");
        $importer->sqlDate('03/04/26');
    }

    public function testDocReferenceIsTheFirstMatchOfTheLinesExpression(): void
    {
        $importer = $this->importer(ImportFileTypeModel::DTF_YYYYMMDD);
        $line = Models::line(['docField' => 'Payee', 'docMatch' => '/INV-\d+/']);

        $this->assertSame('INV-0042', $importer->docReference($this->row('Payment INV-0042 thanks'), $line));
        $this->assertNull($importer->docReference($this->row('Payment, no reference'), $line));
    }

    public function testDocReferenceIsNullWithoutAnExpression(): void
    {
        $importer = $this->importer(ImportFileTypeModel::DTF_YYYYMMDD);
        $line = Models::line(['docField' => 'Payee', 'docMatch' => '']);

        $this->assertNull($importer->docReference($this->row('Payment INV-0042'), $line));
    }

    private function importer(string $dateFormat): Importer
    {
        return Importer::fromPartyType(
            ImportLineModel::PT_SUPPLIER,
            Models::fileType(['dateFormat' => $dateFormat]),
            new ImportState()
        );
    }

    private function row(string $payee): Row
    {
        $row = new Row();
        $row->data = ['2026-03-04', '-10.00', $payee];

        return $row;
    }
}

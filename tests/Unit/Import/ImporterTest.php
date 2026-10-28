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

    public function testSqlDateKeepsYyyyMmDd(): void
    {
        $importer = $this->importer(ImportFileTypeModel::DTF_YYYYMMDD);

        $this->assertSame('2026-03-04', $importer->sqlDate('2026-03-04'));
    }

    /**
     * @return array<string, array{string}>
     */
    public function notYyyyMmDd(): array
    {
        return [
            'dd/mm/yyyy' => ['06/08/2026'],
            'dd/mm/yy' => ['08/09/26'],
            'no such day' => ['2026-02-30'],
            'empty' => [''],
        ];
    }

    /**
     * BNZ's exports changed from 2026-07-07 to 06/08/2026 to 08/09/26, and MySQL
     * stored those as 0000-00-00 and 2008-09-26.
     *
     * @dataProvider notYyyyMmDd
     */
    public function testSqlDateRefusesWhatIsNotAYyyyMmDdDate(string $date): void
    {
        $importer = $this->importer(ImportFileTypeModel::DTF_YYYYMMDD);

        $this->expectExceptionMessage("Bad date '$date'");
        $importer->sqlDate($date);
    }

    public function testSqlDateReadsDdMmYy(): void
    {
        $importer = $this->importer(ImportFileTypeModel::DTF_DDMMYY);

        $this->assertSame('2026-03-04', $importer->sqlDate('04/03/26'));
    }

    public function testSqlDateRefusesADdMmYyDateOfAnotherLength(): void
    {
        $importer = $this->importer(ImportFileTypeModel::DTF_DDMMYY);

        $this->expectExceptionMessage("Bad date '4/3/26'");
        $importer->sqlDate('4/3/26');
    }

    public function testSqlDateRefusesEightCharactersThatAreNotADdMmYyDate(): void
    {
        $importer = $this->importer(ImportFileTypeModel::DTF_DDMMYY);

        $this->expectExceptionMessage("Bad date '2026-3-4'");
        $importer->sqlDate('2026-3-4');
    }

    public function testSqlDateRefusesADdMmYyDateThatDoesNotExist(): void
    {
        $importer = $this->importer(ImportFileTypeModel::DTF_DDMMYY);

        $this->expectExceptionMessage("Bad date '31/09/26'");
        $importer->sqlDate('31/09/26');
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

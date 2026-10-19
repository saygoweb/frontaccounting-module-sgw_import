<?php

namespace SGW_Import\Tests\Integration\Controller;

use SGW_Import\Controller\ImportLine;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Tests\Integration\FaTestCase;

/**
 * Edit Line's controller.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ImportLineTest extends FaTestCase
{
    public function testRunNeedsALine(): void
    {
        $this->expectExceptionMessage('id not set');
        (new ImportLine($this->view(['view'])))->run();
    }

    public function testALineWhoseBankAccountHasNoFileTypeIsRefused(): void
    {
        $line = $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius');
        $_GET = ['id' => $line->id];

        $this->expectExceptionMessage("Import file type for bank id '1' not found");
        (new ImportLine($this->view(['view'])))->run();
    }

    public function testTheLineIsShownWithItsFileType(): void
    {
        $this->fileType();
        $line = $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius');
        $_GET = ['id' => $line->id];
        $view = $this->view(['view']);
        $view->expects($this->once())->method('view')->with(
            $this->callback(function (ImportLineModel $shown) use ($line) {
                return (string) $shown->id === (string) $line->id && $shown->partyMatch === 'Dino Saurius';
            }),
            $this->isInstanceOf(ImportFileTypeModel::class)
        );

        (new ImportLine($view))->run();
    }

    public function testUpdateWritesTheLineWithItsPartysCode(): void
    {
        $this->fileType();
        $line = $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Phone Co');

        $this->post($line, [
            'party_type' => ImportLineModel::PT_QUICK_PAYMENT,
            'party_id' => '2',
            'doc_type' => ImportLineModel::DT_NONE,
            'stock_id' => '301',
            'UPDATE_ITEM' => 'Update',
        ]);

        $read = $this->read($line);
        $this->assertSame(ImportLineModel::PT_QUICK_PAYMENT, $read->partyType);
        $this->assertSame('2', (string) $read->partyId);
        $this->assertSame('Phone', $read->partyCode);
        $this->assertSame('Payee', $read->partyField);
        $this->assertSame('Memo', $read->docField);
        $this->assertSame('', (string) $read->docCode, 'no item without a supplier invoice');
    }

    public function testASupplierInvoiceLineKeepsItsItem(): void
    {
        $this->fileType();
        $line = $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius');

        $this->post($line, [
            'party_type' => ImportLineModel::PT_SUPPLIER,
            'party_id' => '1',
            'doc_type' => ImportLineModel::DT_SUPPLIER_INVOICE,
            'stock_id' => '301',
            'UPDATE_ITEM' => 'Update',
        ]);

        $read = $this->read($line);
        $this->assertSame('301', $read->docCode);
        $this->assertSame('Dino Saurius', $read->partyCode);
    }

    public function testResetShowsTheLineAsWritten(): void
    {
        $this->fileType();
        $line = $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius');

        $shown = $this->post($line, [
            'party_type' => ImportLineModel::PT_CUSTOMER,
            'party_id' => '1',
            'doc_type' => ImportLineModel::DT_NONE,
            'stock_id' => '',
            'RESET' => 'Cancel',
        ]);

        $this->assertSame(ImportLineModel::PT_SUPPLIER, $shown->partyType);
        $this->assertSame(ImportLineModel::PT_SUPPLIER, $this->read($line)->partyType);
    }

    public function testChangingThePartyTypeShowsItUnwritten(): void
    {
        $this->fileType();
        $line = $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius');

        $shown = $this->post($line, [
            '_party_type_update' => '1',
            'party_type' => ImportLineModel::PT_TRANSFER,
            'party_id' => '2',
            'doc_type' => ImportLineModel::DT_NONE,
            'stock_id' => '',
        ]);

        $this->assertSame(ImportLineModel::PT_TRANSFER, $shown->partyType);
        $this->assertSame(ImportLineModel::PT_SUPPLIER, $this->read($line)->partyType);
    }

    /**
     * @return array<string, array{string, int, string}>
     */
    public function partyCodes(): array
    {
        return [
            'customer' => [ImportLineModel::PT_CUSTOMER, 1, 'Donald Easter'],
            'supplier' => [ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius'],
            'quick payment' => [ImportLineModel::PT_QUICK_PAYMENT, 2, 'Phone'],
            'quick deposit' => [ImportLineModel::PT_QUICK_DEPOSIT, 3, 'Cash Sales'],
            'transfer' => [ImportLineModel::PT_TRANSFER, 2, 'Petty Cash account'],
        ];
    }

    /**
     * @dataProvider partyCodes
     */
    public function testPartyCodeIsThePartysOwnCode(string $partyType, int $partyId, string $code): void
    {
        $this->assertSame($code, (new ImportLine($this->view(['view'])))->partyCode($partyType, $partyId));
    }

    public function testPartyCodeRefusesAnUnknownPartyType(): void
    {
        $this->expectExceptionMessage('Unsupported Party Type: nobody');
        (new ImportLine($this->view(['view'])))->partyCode('nobody', 1);
    }

    /**
     * Edit Line posted for $line with $fields, as its form sends them: the
     * line's own fields, the columns as their positions in the file type.
     *
     * @param array<string, string> $fields
     * @return ImportLineModel the line the view was given
     */
    private function post(ImportLineModel $line, array $fields): ImportLineModel
    {
        $_POST = $fields + [
            'id' => (string) $line->id,
            'bank_id' => (string) $line->bankId,
            'party_match' => $line->partyMatch,
            'party_code' => '',
            'party_field' => '2',
            'doc_field' => '3',
            'doc_match' => '',
            'doc_code' => '',
        ];
        $shown = null;
        $view = $this->view(['view']);
        $view->method('view')->willReturnCallback(function (ImportLineModel $model) use (&$shown) {
            $shown = clone $model;
        });

        (new ImportLine($view))->run();

        $this->assertNotNull($shown, 'the line was shown');

        return $shown;
    }

    private function read(ImportLineModel $line): ImportLineModel
    {
        $read = new ImportLineModel();
        $read->readOrThrow($line->id);

        return $read;
    }
}

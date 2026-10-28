<?php

namespace SGW_Import\Tests\Integration\Controller;

use SGW_Import\Controller\AdminFileType;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Tests\Integration\FaTestCase;

/**
 * Configure Import Transactions' controller.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AdminFileTypeTest extends FaTestCase
{
    public function testWithoutAFileTypeThereIsNothingToConfigure(): void
    {
        $this->expectExceptionMessage('No import file types found');
        (new AdminFileType($this->view(['view', 'lineRow'])))->run();
    }

    public function testAFileTypeIsShownToStartWith(): void
    {
        $fileType = $this->fileType(self::PETTY_CASH);

        $this->assertSame((string) $fileType->id, (string) $this->submit([])->id);
    }

    public function testChoosingABankAccountShowsItsFileType(): void
    {
        $this->fileType(self::BANK);
        $petty = $this->fileType(self::PETTY_CASH);

        $shown = $this->submit(['_bank_id_update' => '1', 'bank_id' => (string) self::PETTY_CASH]);

        $this->assertSame((string) $petty->id, (string) $shown->id);
    }

    public function testUpdateWritesTheColumnsChosen(): void
    {
        $fileType = $this->fileType();

        $this->submit([
            'id' => (string) $fileType->id,
            'hide' => 'Payee,Memo',
            'date_field' => '3',
            'date_format' => ImportFileTypeModel::DTF_DDMMYY,
            'amount_field' => '1',
            'UPDATE_ITEM' => 'Update',
        ]);

        $read = ImportFileTypeModel::findByBankId(self::BANK);
        $this->assertSame(['Payee', 'Memo'], $read->hide);
        $this->assertSame('Memo', $read->dateField);
        $this->assertSame(ImportFileTypeModel::DTF_DDMMYY, $read->dateFormat);
        $this->assertSame('Amount', $read->amountField);
    }

    public function testResetShowsTheFileTypeAsWritten(): void
    {
        $fileType = $this->fileType();

        $shown = $this->submit([
            'id' => (string) $fileType->id,
            'hide' => '',
            'date_field' => '3',
            'date_format' => ImportFileTypeModel::DTF_DDMMYY,
            'amount_field' => '1',
            'RESET' => 'Cancel',
        ]);

        $this->assertSame('Date', $shown->dateField);
        $this->assertSame('Date', ImportFileTypeModel::findByBankId(self::BANK)->dateField);
    }

    public function testTheFileTypeReadsBackForTheForm(): void
    {
        $fileType = $this->fileType();
        $controller = new AdminFileType($this->view(['view', 'lineRow']));

        $this->assertSame('Date,Amount,Payee,Memo', $controller->columns($fileType));
        $this->assertSame('Memo', $controller->hide($fileType));
        $this->assertSame(
            [ImportFileTypeModel::DTF_YYYYMMDD, ImportFileTypeModel::DTF_DDMMYY],
            array_keys($controller->dateFormatOptions())
        );
    }

    public function testTheLinesTableHasARowForEachOfTheBankAccountsLines(): void
    {
        $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius');
        $this->line(ImportLineModel::PT_QUICK_PAYMENT, 2, 'Phone Co');
        $matches = [];
        $view = $this->view(['view', 'lineRow']);
        $view->method('lineRow')->willReturnCallback(function (ImportLineModel $line) use (&$matches) {
            $matches[] = $line->partyMatch;
        });

        (new AdminFileType($view))->linesTable(self::BANK);

        sort($matches);
        $this->assertSame(['Dino Saurius', 'Phone Co'], $matches);
    }

    public function testDeleteRemovesTheLine(): void
    {
        $fileType = $this->fileType();
        $this->line(ImportLineModel::PT_SUPPLIER, 1, 'Dino Saurius');
        $phone = $this->line(ImportLineModel::PT_QUICK_PAYMENT, 2, 'Phone Co');
        $activated = [];
        $ajax = $this->createMock(\Ajax::class);
        $ajax->method('activate')->willReturnCallback(function ($name) use (&$activated) {
            $activated[] = $name;
        });
        $GLOBALS['Ajax'] = $ajax;

        $this->submit([
            'id' => (string) $fileType->id,
            'hide' => '',
            'date_field' => '0',
            'date_format' => ImportFileTypeModel::DTF_YYYYMMDD,
            'amount_field' => '1',
            'Delete' . $phone->id => 'Delete',
        ]);

        $matches = [];
        foreach (ImportLineModel::findByBankId(self::BANK) as $line) {
            $matches[] = $line->partyMatch;
        }
        $this->assertSame(['Dino Saurius'], $matches);
        $this->assertContains('_page_body', $activated, 'the page is redrawn over ajax');
    }

    /**
     * run() with $post.
     *
     * @param array<string, string> $post
     * @return ImportFileTypeModel the file type the view was given
     */
    private function submit(array $post): ImportFileTypeModel
    {
        $_POST = $post;
        $shown = null;
        $view = $this->view(['view', 'lineRow']);
        $view->method('view')->willReturnCallback(function (ImportFileTypeModel $model) use (&$shown) {
            $shown = clone $model;
        });

        (new AdminFileType($view))->run();

        $this->assertNotNull($shown, 'the file type was shown');

        return $shown;
    }
}

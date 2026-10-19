<?php

namespace SGW_Import\Tests\Integration\Controller;

use FA\GraphQL\Fa\FaMessages;
use SGW_Import\Controller\ImportFile;
use SGW_Import\Import\Column;
use SGW_Import\Import\CsvFile;
use SGW_Import\Import\Row;
use SGW_Import\Import\RowStatus;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Tests\Integration\FaTestCase;

/**
 * An imported file's controller: its rows, Add Line, and the import itself.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ImportFileTest extends FaTestCase
{
    /** Demo quick entry 2: Phone, a bank payment. */
    private const QUICK_PAYMENT = 2;

    private int $fileId;

    private string $date;

    /** @var array<int, array{row: Row, line: ?ImportLineModel, doImport: bool}> by row index */
    private array $rows = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileType();
        $this->date = $this->date(50);
        $this->fileId = (int) $this->importFile(
            "Date,Amount,Payee,Memo\n"
            . "{$this->date},-43.21,Phone Co,March\n"
            . "{$this->date},-1.00,Unknown,Other\n"
        )->id;
    }

    public function testRunNeedsAFile(): void
    {
        $this->expectExceptionMessage('id not set');
        (new ImportFile($this->view(['viewList', 'displayError'])))->run();
    }

    public function testRunShowsTheFileWithTheFileTypesColumns(): void
    {
        $_GET = ['id' => $this->fileId];
        $view = $this->view(['viewList', 'displayError']);
        $view->expects($this->never())->method('displayError');
        $view->expects($this->once())->method('viewList')->with(
            $this->callback(function (CsvFile $file) {
                return (int) $file->id === $this->fileId && $file->columns === ['Date', 'Amount', 'Payee', 'Memo'];
            }),
            $this->callback(function (array $columns) {
                return array_map(function (Column $c) {
                    return [$c->name, $c->hidden];
                }, $columns) === [['Date', false], ['Amount', false], ['Payee', false], ['Memo', true]];
            })
        );

        (new ImportFile($view))->run();
    }

    public function testAFileThatIsGoneIsReported(): void
    {
        $_GET = ['id' => 999999];
        $view = $this->view(['viewList', 'displayError']);
        $view->expects($this->never())->method('viewList');
        $view->expects($this->once())->method('displayError');

        (new ImportFile($view))->run();
    }

    public function testAddLineMakesALineForTheRowsPayee(): void
    {
        $_POST = ['id' => $this->fileId, 'a_2' => 'Add Line'];

        (new ImportFile($this->view(['viewList', 'displayError'])))->run();

        $lines = $this->lines();
        $this->assertCount(1, $lines);
        $this->assertSame(['Unknown', 'Payee'], [$lines[0]->partyMatch, $lines[0]->partyField]);
    }

    public function testTheTableMatchesEachRowToItsLine(): void
    {
        $line = $this->line(ImportLineModel::PT_QUICK_PAYMENT, self::QUICK_PAYMENT, 'Phone Co');

        $this->table([]);

        $this->assertSame([1, 2], array_keys($this->rows));
        $this->assertSame((string) $line->id, (string) $this->rows[1]['line']->id);
        $this->assertNull($this->rows[2]['line']);
        $this->assertFalse($this->rows[1]['doImport']);
        $this->assertSame([], FaMessages::errors());
    }

    public function testImportWritesTheSelectedRows(): void
    {
        $this->line(ImportLineModel::PT_QUICK_PAYMENT, self::QUICK_PAYMENT, 'Phone Co');

        $this->table(['submit_import' => 'Import Data', 's_1' => '1']);

        $this->assertSame(RowStatus::STATUS_NEW, $this->rows[1]['row']->status->status);
        $this->assertSame(1, $this->payments());
    }

    public function testADryRunWritesNothing(): void
    {
        $this->line(ImportLineModel::PT_QUICK_PAYMENT, self::QUICK_PAYMENT, 'Phone Co');

        $this->table(['submit_import' => 'Import Data', 's_1' => '1', 'dry_run' => '1']);

        $this->assertSame(RowStatus::STATUS_TODO, $this->rows[1]['row']->status->status);
        $this->assertSame(0, $this->payments());
    }

    public function testImportingNothingSelectedIsReported(): void
    {
        $this->table(['submit_import' => 'Import Data']);

        $this->assertSame(['No lines selected for import.'], FaMessages::errors());
    }

    public function testSelectAllTicksEveryRow(): void
    {
        $this->table(['_select_all_update' => '1', 'select_all' => '1']);

        $this->assertSame('1', $_POST['s_1']);
        $this->assertSame('1', $_POST['s_2']);
    }

    public function testColumnHiddenFollowsTheFileType(): void
    {
        $_GET = ['id' => $this->fileId];
        $controller = new ImportFile($this->view(['viewList', 'displayError']));
        $controller->run();

        $this->assertTrue($controller->columnHidden('Memo'));
        $this->assertFalse($controller->columnHidden('Payee'));
    }

    /**
     * run() with $post, its view rendering the table as the page does,
     * recording each row.
     *
     * @param array<string, string> $post
     */
    private function table(array $post): void
    {
        $_POST = ['id' => (string) $this->fileId] + $post;
        $view = $this->view(['viewList', 'displayError', 'tableRow']);
        $controller = new ImportFile($view);
        $view->method('viewList')->willReturnCallback(function (CsvFile $file, array $columns) use ($controller) {
            $controller->table($columns);
        });
        $view->method('displayError')->willReturnCallback(function (string $message) {
            $this->fail($message);
        });
        $view->method('tableRow')->willReturnCallback(
            function (Row $row, ?ImportLineModel $line, array $columns, $doImport) {
                $this->rows[$row->rowIndex] = ['row' => clone $row, 'line' => $line, 'doImport' => (bool) $doImport];
                $this->rows[$row->rowIndex]['row']->status = clone $row->status;
            }
        );

        $controller->run();
    }

    /**
     * @return ImportLineModel[]
     */
    private function lines(): array
    {
        $lines = [];
        foreach (ImportLineModel::findByBankId(self::BANK) as $line) {
            $lines[] = clone $line;
        }

        return $lines;
    }

    private function payments(): int
    {
        return (int) $this->pdo()->query(
            "SELECT COUNT(*) FROM 0_bank_trans WHERE type = 1 AND bank_act = 1
             AND trans_date = '{$this->date}' AND amount = -43.21"
        )->fetchColumn();
    }
}

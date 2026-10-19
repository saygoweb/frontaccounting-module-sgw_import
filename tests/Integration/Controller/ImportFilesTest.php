<?php

namespace SGW_Import\Tests\Integration\Controller;

use FA\GraphQL\Fa\FaMessages;
use SGW_Import\Controller\ImportFiles;
use SGW_Import\Model\ImportFileListModel;
use SGW_Import\Model\ImportFileModel;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Tests\Integration\FaTestCase;

/**
 * Import Transactions' controller: uploads, deletes, and the file list.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ImportFilesTest extends FaTestCase
{
    public function testTheListAndTheUploadFormAreShown(): void
    {
        $view = $this->view(['viewList', 'addFile']);
        $view->expects($this->once())->method('viewList');
        $view->expects($this->once())->method('addFile');

        (new ImportFiles($view))->run();
    }

    public function testAnUploadIsStoredRecordedAndGivesTheBankAccountAFileType(): void
    {
        $name = $this->upload("Date,Amount,Payee,Memo\n2026-01-05,-1.00,A,B\n");

        (new ImportFiles($this->view(['viewList', 'addFile'])))->run();

        $this->assertSame([], FaMessages::errors());
        $this->assertFileExists($this->uploads()->path($name));
        $file = ImportFileModel::findByFileName($name);
        $this->assertNotFalse($file);
        $this->assertSame((string) self::PETTY_CASH, (string) $file->bankId);
        $fileType = ImportFileTypeModel::findByBankId(self::PETTY_CASH);
        $this->assertSame(['Date', 'Amount', 'Payee', 'Memo'], $fileType->columns);
    }

    public function testAnUploadKeepsTheBankAccountsFileType(): void
    {
        $this->fileType(self::PETTY_CASH);
        $this->upload("Other,Columns\n");

        (new ImportFiles($this->view(['viewList', 'addFile'])))->run();

        $this->assertSame(
            ['Date', 'Amount', 'Payee', 'Memo'],
            ImportFileTypeModel::findByBankId(self::PETTY_CASH)->columns
        );
    }

    public function testUploadingAFileAgainUpdatesItsRecord(): void
    {
        $name = $this->upload("Date,Amount,Payee\n");
        (new ImportFiles($this->view(['viewList', 'addFile'])))->run();
        $first = ImportFileModel::findByFileName($name);

        $this->upload("Date,Amount,Payee\n2026-01-05,-1.00,A\n", $name, self::BANK);
        (new ImportFiles($this->view(['viewList', 'addFile'])))->run();

        $again = ImportFileModel::findByFileName($name);
        $this->assertSame((string) $first->id, (string) $again->id);
        $this->assertSame((string) self::BANK, (string) $again->bankId);
        $this->assertStringEndsWith("A\n", (string) file_get_contents($this->uploads()->path($name)));
    }

    public function testAnUploadWithoutAFileIsRefused(): void
    {
        $_POST = ['upload' => 'Upload', 'bank_id' => self::BANK];
        $_FILES = ['filename' => ['name' => '', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE]];

        (new ImportFiles($this->view(['viewList', 'addFile'])))->run();

        $this->assertSame(['Choose a file before uploading'], FaMessages::errors());
    }

    public function testDeleteRemovesTheFileAndItsRecord(): void
    {
        $file = $this->importFile("Date,Amount,Payee\n");
        $_POST = ['d_' . $file->id => 'Delete'];

        (new ImportFiles($this->view(['viewList', 'addFile'])))->run();

        $this->assertFalse(ImportFileModel::findByFileName($file->fileName));
        $this->assertFileDoesNotExist($this->uploads()->path($file->fileName));
    }

    public function testDeleteRemovesTheRecordOfAFileThatIsGone(): void
    {
        $file = $this->importFile("Date,Amount,Payee\n");
        unlink($this->uploads()->path($file->fileName));
        $_POST = ['d_' . $file->id => 'Delete'];

        (new ImportFiles($this->view(['viewList', 'addFile'])))->run();

        $this->assertFalse(ImportFileModel::findByFileName($file->fileName));
    }

    public function testDeleteAllShowsNothing(): void
    {
        $view = $this->view(['viewList', 'addFile']);
        $view->expects($this->never())->method('viewList');
        $_POST = ['delete_all' => 'Delete', 's_1' => '1'];

        (new ImportFiles($view))->run();
    }

    public function testTheTableHasARowForEachFile(): void
    {
        $first = $this->importFile("Date,Amount,Payee\n");
        $second = $this->importFile("Date,Amount,Payee\n", self::PETTY_CASH);
        $rows = [];
        $view = $this->view(['viewList', 'addFile', 'tableRow']);
        $view->method('tableRow')->willReturnCallback(function (ImportFileListModel $model) use (&$rows) {
            $rows[$model->fileName] = $model->bankAccountName;
        });

        (new ImportFiles($view))->table();

        $this->assertSame(
            [$first->fileName => 'Current account', $second->fileName => 'Petty Cash account'],
            $rows
        );
    }

    public function testSelectAllTicksEveryFile(): void
    {
        $file = $this->importFile("Date,Amount,Payee\n");
        $_POST = ['_select_all_update' => '1', 'select_all' => '1'];
        $view = $this->view(['viewList', 'addFile', 'tableRow']);
        $controller = new ImportFiles($view);
        $view->method('viewList')->willReturnCallback(function () use ($controller) {
            $controller->table();
        });

        $controller->run();

        $this->assertSame('1', $_POST['s_' . $file->id]);
    }

    /**
     * $csv as PHP leaves an upload, posted for the bank account.
     */
    private function upload(string $csv, ?string $name = null, int $bankId = self::PETTY_CASH): string
    {
        $name = $name ?? $this->uploadName();
        $tmp = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($tmp, $csv);
        $_POST = ['upload' => 'Upload', 'bank_id' => $bankId];
        $_FILES = ['filename' => ['name' => $name, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK]];

        return $name;
    }
}

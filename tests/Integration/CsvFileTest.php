<?php

namespace SGW_Import\Tests\Integration;

use SGW_Import\Import\CsvFile;
use SGW_Import\Model\ImportFileModel;
use SGW_Import\Tests\Support\UploadFolder;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CsvFileTest extends FaTestCase
{
    private UploadFolder $folder;

    private string $name;

    protected function setUp(): void
    {
        parent::setUp();
        $this->folder = new UploadFolder();
        $this->name = $this->folder->uniqueName();
    }

    protected function tearDown(): void
    {
        if (is_file($this->folder->path($this->name))) {
            unlink($this->folder->path($this->name));
        }
        $this->folder->remove();
        parent::tearDown();
    }

    public function testRowsAreReadInOrderAfterTheHeader(): void
    {
        $csv = new CsvFile($this->import(
            "Date,Amount,Payee\n2026-01-05,-10.00,\"Phone, Co\"\n2026-01-06,25.50,Customer\n"
        ));

        $this->assertSame(['Date', 'Amount', 'Payee'], $csv->columns);
        $first = $csv->read();
        $this->assertSame(1, $first->rowIndex);
        $this->assertSame(['2026-01-05', '-10.00', 'Phone, Co'], $first->data);
        $second = $csv->read();
        $this->assertSame(2, $second->rowIndex);
        $this->assertSame(['2026-01-06', '25.50', 'Customer'], $second->data);
        $this->assertNull($csv->read());
        $csv->close();
    }

    public function testReadRowReadsOneRowFromTheStartAfterAReset(): void
    {
        $csv = new CsvFile($this->import("Date,Amount,Payee\n2026-01-05,-1,A\n2026-01-06,-2,B\n2026-01-07,-3,C\n"));
        $csv->read();
        $csv->read();

        $csv->reset();
        $row = $csv->readRow(3);

        $this->assertSame(3, $row->rowIndex);
        $this->assertSame(['2026-01-07', '-3', 'C'], $row->data);
    }

    public function testAFileThatIsNotRecordedIsRefused(): void
    {
        $this->expectException(\Exception::class);
        new CsvFile(999999);
    }

    private function import(string $contents): int
    {
        file_put_contents($this->folder->path($this->name), $contents);
        $file = new ImportFileModel();
        $file->bankId = self::BANK;
        $file->fileName = $this->name;
        $file->write();

        return (int) $file->id;
    }
}

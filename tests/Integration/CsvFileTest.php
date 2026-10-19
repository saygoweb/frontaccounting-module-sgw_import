<?php

namespace SGW_Import\Tests\Integration;

use SGW_Import\Import\CsvFile;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CsvFileTest extends FaTestCase
{
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
        return (int) $this->importFile($contents)->id;
    }
}

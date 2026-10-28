<?php

namespace SGW_Import\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use SGW_Import\Tests\Support\Models;

class ImportFileTypeModelTest extends TestCase
{
    public function testColumnKeyIsTheColumnsPosition(): void
    {
        $fileType = Models::fileType(['columns' => ['Date', 'Amount', 'Payee']]);

        $this->assertSame(0, $fileType->columnKey('Date'));
        $this->assertSame(2, $fileType->columnKey('Payee'));
    }

    public function testColumnKeyRefusesAColumnTheFileLacks(): void
    {
        $fileType = Models::fileType(['columns' => ['Date', 'Amount', 'Payee']]);

        $this->expectExceptionMessage("Column 'Memo' not found");
        $fileType->columnKey('Memo');
    }

    public function testFieldKeyIsTheBlankColumnForNoField(): void
    {
        $fileType = Models::fileType(['columns' => ['Date', 'Amount', 'Payee', '']]);

        $this->assertSame(2, $fileType->fieldKey('Payee'));
        $this->assertSame(3, $fileType->fieldKey(''));
        $this->assertSame(3, $fileType->fieldKey(null));
    }

    public function testFieldKeyIsNullForAColumnTheFileLacks(): void
    {
        $fileType = Models::fileType(['columns' => ['Date', 'Amount', 'Payee']]);

        $this->assertNull($fileType->fieldKey('Memo'));
        $this->assertNull($fileType->fieldKey(''));
    }
}

<?php

namespace SGW_Import\Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use SGW_Import\View\View;

class ViewTest extends TestCase
{
    protected function tearDown(): void
    {
        $_POST = [];
    }

    public function testComboSelectsTheValueGiven(): void
    {
        $html = View::combo('format', ['yyyymmdd' => 'YYYY-MM-DD', 'ddmmyy' => 'DD/MM/YY'], 'ddmmyy');

        $this->assertStringStartsWith('<select name="format" id="format">', $html);
        $this->assertStringContainsString('<option value="yyyymmdd">YYYY-MM-DD</option>', $html);
        $this->assertStringContainsString('<option value="ddmmyy" selected>DD/MM/YY</option>', $html);
    }

    public function testComboFallsBackToThePostedValue(): void
    {
        $_POST['format'] = 'yyyymmdd';

        $html = View::combo('format', ['yyyymmdd' => 'YYYY-MM-DD', 'ddmmyy' => 'DD/MM/YY']);

        $this->assertStringContainsString('<option value="yyyymmdd" selected>', $html);
        $this->assertStringContainsString('<option value="ddmmyy">', $html);
    }
}

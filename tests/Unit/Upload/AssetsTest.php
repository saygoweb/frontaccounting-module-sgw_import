<?php

namespace SGW_Import\Tests\Unit\Upload;

use PHPUnit\Framework\TestCase;
use SGW_Import\Tests\Support\UploadFolder;
use SGW_Import\Upload\Assets;

class AssetsTest extends TestCase
{
    private UploadFolder $folder;

    private string $name;

    protected function setUp(): void
    {
        $this->folder = new UploadFolder();
        $this->name = $this->folder->uniqueName();
        $tmp = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($tmp, "Date,Amount,Payee\n");
        $_FILES = ['file' => ['name' => $this->name, 'tmp_name' => $tmp]];
    }

    protected function tearDown(): void
    {
        clearstatcache();
        if (is_file($_FILES['file']['tmp_name'])) {
            unlink($_FILES['file']['tmp_name']);
        }
        if (is_file($this->folder->path($this->name))) {
            unlink($this->folder->path($this->name));
        }
        $this->folder->remove();
        $_FILES = [];
    }

    public function testAnUploadedFileIsStoredUnderItsOwnName(): void
    {
        $assets = new Assets();
        $this->assertSame($this->name, $assets->filename('file'));
        $this->assertFalse($assets->exists('file'));

        $this->assertSame($this->name, $assets->storeUploadedFile('file'));

        $this->assertTrue($assets->exists('file'));
        $this->assertSame(realpath($this->folder->path($this->name)), realpath($assets->filePath($this->name)));
        $this->assertSame("Date,Amount,Payee\n", file_get_contents($assets->filePath($this->name)));
    }

    public function testDeleteRemovesAStoredFile(): void
    {
        $assets = new Assets();
        $assets->storeUploadedFile('file');

        $this->assertTrue($assets->delete($this->name));

        $this->assertFalse($assets->exists('file'));
    }

    public function testDeletingAFileThatIsNotThereIsRefused(): void
    {
        $this->expectExceptionMessage("File '{$this->name}' not found");
        (new Assets())->delete($this->name);
    }
}

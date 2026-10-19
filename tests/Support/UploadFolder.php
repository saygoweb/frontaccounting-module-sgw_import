<?php

namespace SGW_Import\Tests\Support;

/**
 * The module's uploads/ folder, where Assets keeps imported files. It is not in
 * the repository (.gitignore), so a test that needs it makes it, and removes it
 * again if it made it; files a test puts there it removes itself.
 */
final class UploadFolder
{
    private string $path;

    private bool $made = false;

    public function __construct()
    {
        $this->path = dirname(__DIR__, 2) . '/uploads';
        if (!is_dir($this->path)) {
            mkdir($this->path);
            // Apache stores uploads here too, as another user.
            chmod($this->path, 0777);
            $this->made = true;
        }
    }

    public function path(string $fileName): string
    {
        return $this->path . '/' . $fileName;
    }

    /**
     * A file name no other test run uses.
     */
    public function uniqueName(string $extension = 'csv'): string
    {
        return 'sgw-import-test-' . bin2hex(random_bytes(6)) . '.' . $extension;
    }

    public function remove(): void
    {
        if ($this->made && is_dir($this->path) && count(scandir($this->path)) === 2) {
            rmdir($this->path);
        }
    }
}

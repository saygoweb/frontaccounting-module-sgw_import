<?php
namespace SGW_Import\Upload;

class Assets
{
    /** @var string */
    private $uploadFolder;

    public function __construct()
    {
        $moduleRootPath = __DIR__ . '/../..';
        $this->uploadFolder = realpath($moduleRootPath) . '/uploads';
    }

    /** 
     * @param string
     * @return boolean
     */
    public function exists(string $fileIndex)
    {
        return file_exists($this->filePathFromIndex($fileIndex));
    }

    /**
     * @return string
     */
    public function storeUploadedFile(string $fileIndex)
    {
        if (!file_exists($this->uploadFolder)) {
            throw new \Exception("Folder '" . $this->uploadFolder . "' not found.");
        }
       
        $fileName = $this->filename($fileIndex);
        $filePath = $this->filePathFromIndex($fileIndex);

        $fileInfo = $_FILES[$fileIndex];
        rename($fileInfo['tmp_name'], $filePath);

        return $fileName;
    }

    public function filePath(string $fileName)
    {
        return $this->uploadFolder . DIRECTORY_SEPARATOR . $fileName;
    }

    /**
     * @return string
     */
    private function filePathFromIndex(string $fileIndex)
    {
        return $this->uploadFolder . DIRECTORY_SEPARATOR . $this->filename($fileIndex);
    }

    /**
     * @return string
     */
    public function filename(string $fileIndex)
    {
        $fileInfo = $_FILES[$fileIndex];
        $pathInfo = pathinfo($fileInfo['name']);
        // $random = bin2hex(random_bytes(4)); // see http://php.net/manual/en/function.random-bytes.php
        $fileName = sprintf('%s.%s', $pathInfo['filename'], $pathInfo['extension']);
        return $fileName;
    }

    public function delete(string $fileName)
    {
        $filePath = $this->uploadFolder . DIRECTORY_SEPARATOR . $fileName;
        if (!file_exists($filePath)) {
            throw new \Exception(sprintf("File '%s' not found", $fileName));
        }
        return unlink($filePath);
    }

}
<?php
namespace SGW_Import\Model;

use Anorm\Anorm;
use Anorm\DataMapper;
use Anorm\Model;
use SGW\DB;

class ImportFileModel extends Model
{
    public function __construct()
    {
        $pdo = Anorm::pdo();
        parent::__construct($pdo, DataMapper::createByClass($pdo, $this, DB::tablePrefix()));
        $this->_mapper->mode = DataMapper::MODE_DYNAMIC;
    }

    /**
     * @return ImportFileModel|bool
     */
    public static function findByFileName($fileName)
    {
        $result = DataMapper::find(ImportFileModel::class, Anorm::pdo())
            ->where('file_name=:fileName', [':fileName' => $fileName])
            ->one();
        return $result;
    }

    /** 
     * @return bool
     */
    public static function delete($id)
    {
        $model = new ImportFileModel();
        return $model->_mapper->delete($id);
    }

    /** @var int */
    public $id;

    /** @var int */
    public $bankId;

    /** @var string */
    public $dtImport;

    /** @var string */
    public $fileName;

}

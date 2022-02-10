<?php
namespace SGW_Import\Model;

use Anorm\Anorm;
use Anorm\DataMapper;
use Anorm\Model;
use Anorm\Transform\FunctionTransform;
use SGW\DB;

class ImportFileTypeModel extends Model
{

    const DTF_YYYYMMDD = 'yyyymmdd';
    const DTF_DDMMYY   = 'ddmmyy';

    public function __construct()
    {
        $pdo = Anorm::pdo();
        parent::__construct($pdo, DataMapper::createByClass($pdo, $this, DB::tablePrefix()));
        $this->_mapper->mode = DataMapper::MODE_DYNAMIC;
        $this->_mapper->transformers['columns'] = new FunctionTransform(
            function ($data) {
                return explode(',', $data);
            },
            function ($data) {
                return implode(',', $data);
            }
        );
        $this->_mapper->transformers['hide'] = new FunctionTransform(
            function ($data) {
                return explode(',', $data);
            },
            function ($data) {
                return implode(',', $data);
            }
        );
        $this->columns = [];
        $this->hide = [];
        $this->dateFormat = self::DTF_YYYYMMDD;
    }

    /** @return ImportFileTypeModel | null */
    public static function findByBankId($bankId)
    {
        $result = DataMapper::find(ImportFileTypeModel::class, Anorm::pdo())
            ->where('bank_id=:bankId', [ ':bankId' => $bankId])
            ->one();
        return $result;
    }

    /** @return ImportFileTypeModel | null */
    public static function findOne()
    {
        $result = DataMapper::find(ImportFileTypeModel::class, Anorm::pdo())
            ->limit(1)
            ->one();
        return $result;
    }

    /** 
     * @return bool
     */
    public static function delete($id)
    {
        $model = new ImportFileTypeModel();
        return $model->_mapper->delete($id);
    }

    public function columnKey($name)
    {
        $result = array_search($name, $this->columns);
        if ($result === false) {
            throw new \Exception("Column '$name' not found");
        }
        return $result;
    }

    /** @var int */
    public $id;

    /** @var int */
    public $bankId;

    /** @var string */
    public $dateField;

    /** @var string */
    public $dateFormat;

    /** @var string */
    public $amountField;

    /** @var string[] */
    public $columns;

    /** @var string[] */
    public $hide;

}

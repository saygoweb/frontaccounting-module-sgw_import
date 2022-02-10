<?php
namespace SGW_Import\Model;

use Anorm\Anorm;
use Anorm\DataMapper;
use Anorm\Model;
use SGW\DB;

class ImportLineModel extends Model
{

    const PT_SUPPLIER = 'supplier';
    const PT_CUSTOMER = 'customer';
    const PT_QUICK    = 'quick';
    const PT_TRANSFER = 'transfer';

    const DT_NONE = 'none';
    const DT_SUPPLIER_INVOICE = 'supplier_invoice';
    const DT_CUSTOMER_INVOICE = 'customer_invoice';

    public function __construct()
    {
        $pdo = Anorm::pdo();
        parent::__construct($pdo, DataMapper::createByClass($pdo, $this, DB::tablePrefix()));
        $this->_mapper->mode = DataMapper::MODE_DYNAMIC;
        $this->partyType = self::PT_SUPPLIER;
        $this->docType = self::DT_NONE;
    }

    /**
     * @return Generator<ImportLineModel>|ImportLineModel[]|bool
     */
    public static function findByBankId($bankId)
    {
        $result = DataMapper::find(ImportLineModel::class, Anorm::pdo())
            ->where('bank_id=:bankId', [':bankId' => $bankId])
            ->some();
        return $result;
    }

    public function fixDefaults()
    {
        if ($this->partyType === null) {
            $this->partyType = self::PT_SUPPLIER;
        }
        if ($this->docType === null) {
            $this->docType = self::DT_NONE;
        }
    }

    /** 
     * @return bool
     */
    public static function delete($id)
    {
        $model = new ImportLineModel();
        return $model->_mapper->delete($id);
    }

    /** @var int */
    public $id;

    /** @var int */
    public $bankId;

    /** @var string */
    public $partyField;

    /** @var string */
    public $partyMatch;

    /** @var int */
    public $partyId;

    /** @var string */
    public $partyCode;

    /** @var string */
    public $partyType;

    /** @var string */
    public $docField;

    /** @var string */
    public $docMatch;

    /** @var string */
    public $docCode;

    /** @var string */
    public $docType;

}

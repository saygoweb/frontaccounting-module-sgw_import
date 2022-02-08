<?php
namespace SGW_Import\Model;

use Anorm\Anorm;
use Anorm\DataMapper;
use Anorm\Model;
use SGW\DB;

class PartyCodeModel extends Model
{

    public function __construct()
    {
        $pdo = Anorm::pdo();
        parent::__construct($pdo, DataMapper::createByClass($pdo, $this, DB::tablePrefix()));
        $this->_mapper->mode = DataMapper::MODE_STATIC;
    }

    /**
     * @return PartyCodeModel|null
     */
    public static function findByCustomer($id)
    {
        $result = DataMapper::find(PartyCodeModel::class, Anorm::pdo())
            ->select('debtor_ref AS code')
            ->from(DB::prefix('debtors_master'))
            ->where('debtor_no=:id', [':id' => $id])
            ->oneOrThrow();
        return $result;
    }

    /**
     * @return PartyCodeModel|null
     */
    public static function findBySupplier($id)
    {
        $result = DataMapper::find(PartyCodeModel::class, Anorm::pdo())
            ->select('supp_ref AS code')
            ->from(DB::prefix('suppliers'))
            ->where('supplier_id=:id', [':id' => $id])
            ->oneOrThrow();
        return $result;
    }

    /**
     * @return PartyCodeModel|null
     */
    public static function findByQuick($id)
    {
        $result = DataMapper::find(PartyCodeModel::class, Anorm::pdo())
            ->select('description AS code')
            ->from(DB::prefix('quick_entries'))
            ->where('id=:id', [':id' => $id])
            ->oneOrThrow();
        return $result;
    }

    /**
     * @return PartyCodeModel|null
     */
    public static function findByBank($id)
    {
        $result = DataMapper::find(PartyCodeModel::class, Anorm::pdo())
            ->select('bank_account_name AS code')
            ->from(DB::prefix('bank_accounts'))
            ->where('id=:id', [':id' => $id])
            ->oneOrThrow();
        return $result;
    }



    /** @var int */
    public $id;

    /** @var string */
    public $code;

}

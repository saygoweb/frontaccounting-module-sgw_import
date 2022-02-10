<?php
namespace SGW_Import\Model;

use Anorm\Anorm;
use Anorm\DataMapper;
use Anorm\Model;
use SGW\DB;

class TransactionModel extends Model
{
    public function __construct()
    {
        $pdo = Anorm::pdo();
        parent::__construct($pdo, DataMapper::createByClass($pdo, $this, DB::prefix('')));
        $this->_mapper->mode = DataMapper::MODE_STATIC;
    }

    public static function fromBankTransfer($date, $amount, $from, $to)
    {
        $amount = abs($amount);
        $result = DataMapper::find(TransactionModel::class, Anorm::pdo())
            ->select('trans_no AS number,ref,trans_date AS date,amount,type')
            ->from(DB::prefix('bank_trans'))
            ->where(
                'trans_date=:date AND ABS(amount)=:amount AND (bank_act=:from OR bank_act=:to)',
                [':date' => $date, ':amount' => $amount, ':from' => $from, ':to' => $to]
            )
            ->some();
        return $result;
    }

    public static function fromBankTransaction($date, $amount, $account, $type)
    {
        if ($type == ST_BANKPAYMENT && $amount > 0.0) {
            throw new \Exception('Bank Payment amount must be <= 0.0');
        }
        $result = DataMapper::find(TransactionModel::class, Anorm::pdo())
            ->select('trans_no AS number,ref,trans_date AS date,amount,type')
            ->from(DB::prefix('bank_trans'))
            ->where(
                'trans_date=:date AND amount=:amount AND bank_act=:account',
                [':date' => $date, ':amount' => $amount, ':account' => $account]
            )
            ->some();
        return $result;
    }

    public static function fromBankPaymentAndInvoice($date, $amount, $account, $invoiceRef)
    {
        $fromType = ST_CUSTPAYMENT;
        $bank_trans = DB::prefix('bank_trans');
        $cust_allocations = DB::prefix('cust_allocations');
        $debtor_trans = DB::prefix('debtor_trans');
        $result = DataMapper::find(TransactionModel::class, Anorm::pdo())
            ->select('bt.trans_no AS number,ref,bt.trans_date AS date,bt.amount,bt.type')
            ->from("$bank_trans AS bt")
            ->join("INNER JOIN $cust_allocations AS ca ON bt.type=ca.trans_type_from AND bt.trans_no=ca.trans_no_from")
            ->join("INNER JOIN $debtor_trans AS dt ON ca.trans_type_to=dt.type AND ca.trans_no_to=dt.trans_no")
            ->where(
                'bt.trans_date=:date AND bt.amount=:amount AND bt.bank_act=:account AND dt.reference=:invoiceRef',
                [':date' => $date, ':amount' => $amount, ':account' => $account, ':invoiceRef' => $invoiceRef]
            )
            ->some();
        return $result;
    }

    public static function fromBankPaymentAndPartyId($date, $amount, $account, $partyId)
    {
        $bank_trans = DB::prefix('bank_trans');
        $cust_allocations = DB::prefix('cust_allocations');
        $result = DataMapper::find(TransactionModel::class, Anorm::pdo())
            ->select('bt.trans_no AS number,ref,bt.trans_date AS date,bt.amount,bt.type')
            ->from("$bank_trans AS bt")
            ->join("INNER JOIN $cust_allocations AS ca ON bt.type=ca.trans_type_from AND bt.trans_no=ca.trans_no_from")
            ->where(
                'bt.trans_date=:date AND bt.amount=:amount AND bt.bank_act=:account AND ca.person_id=:partyId',
                [':date' => $date, ':amount' => $amount, ':account' => $account, ':partyId' => $partyId]
            )
            ->some();
        return $result;
    }

    /** @var int */
    public $number;

    /** @var string */
    public $ref;

    /** @var string */
    public $date;

    /** @var int */
    public $type;

    /** @var float */
    public $amount;

}
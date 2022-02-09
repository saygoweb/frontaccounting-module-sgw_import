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
<?php
namespace SGW_Import\Import\Importers;

use SGW_Import\Import\Row;
use SGW_Import\Import\RowStatus;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Model\TransactionModel;

class QuickImporter extends Importer
{
    public function transactionExists(Row $row, ImportLineModel $line, ImportFileTypeModel $fileType)
    {
        $bankId = $fileType->bankId;
        $transactions = TransactionModel::fromBankTransaction($row->data[$this->dateColumn], $row->data[$this->amountColumn], $bankId, ST_BANKPAYMENT);
        $c = 0;
        $t = [];
        foreach ($transactions as $transaction) {
            $t[] = clone $transaction;
            $c++;
        }
        // Status
        if ($c == 1) {
            $row->status->status = RowStatus::STATUS_EXISTING;
            $row->status->documentType = $t[0]->type;
            $row->status->documentId = $t[0]->number;
            $row->status->link = 'gl/view/gl_payment_view.php?trans_no=' . $row->status->documentId;
        }
        
        return $c == 1;
    }

    public function addTransaction(Row $row, ImportLineModel $line, ImportFileTypeModel $fileType)
    {
        global $Refs;
        $faDate = sql2date($row->data[$this->dateColumn]);
        $cart = new \items_cart(ST_BANKPAYMENT);
        $cart->order_id = 0; // Will be set in write_bank_transaction
        $cart->tran_date = $faDate;
        $cart->reference = $Refs->get_next($cart->trans_type, null, $faDate);


        $base = (float)$row->data[$this->amountColumn];
        $base = -$base; // Payments are positive for the fa functions here
        $id = $line->partyId;
        $quickLines = quickentry_calculate($base, $id, @$cart->tax_group_id, $cart->tran_date);
        foreach ($quickLines as $quickLine) {
            $cart->add_gl_item(
                $quickLine['code'], $quickLine['dim1'],
                $quickLine['dim2'], $quickLine['amount'], $quickLine['descr']
            );
        }

        $trans = write_bank_transaction(
            $cart->trans_type, $cart->order_id, $fileType->bankId, $cart, $cart->tran_date,
            4, $line->partyId, '',
            $cart->reference,$line->partyMatch,
            true, null // Note that gl_db_banking L401 has a bogus test for 11 args so use full args here. CP 2022-02
        );

        // Status
        $row->status->status = RowStatus::STATUS_NEW;
        $row->status->documentId = $trans[1];
        $row->status->documentType = ST_BANKPAYMENT;
        $row->status->link = 'gl/view/gl_payment_view.php?trans_no=' . $row->status->documentId;

    }


}

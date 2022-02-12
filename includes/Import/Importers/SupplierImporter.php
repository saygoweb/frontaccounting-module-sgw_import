<?php
namespace SGW_Import\Import\Importers;

use SGW_Import\Import\Row;
use SGW_Import\Import\RowStatus;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Model\TransactionModel;

class SupplierImporter extends Importer
{
    public function transactionExists(Row $row, ImportLineModel $line)
    {
        $bankId = $this->fileType->bankId;
        $sqlDate = $this->sqlDate($row->data[$this->dateColumn]);
        $amount = $row->data[$this->amountColumn];
        $transactions = TransactionModel::fromBankTransaction($sqlDate, $amount, $bankId, ST_SUPPAYMENT);
        $c = 0;
        foreach ($transactions as $transaction) {
            if (!$this->importState->isUsed($sqlDate, $line->partyCode, $amount, $transaction->number)) {
                $row->status->status = RowStatus::STATUS_EXISTING;
                $row->status->documentType = $transaction->type;
                $row->status->documentId = $transaction->number;
                $row->status->link = 'purchasing/view/view_supp_payment.php?trans_no=' . $row->status->documentId;
                $this->importState->pushPartyAmount($sqlDate, $line->partyCode, $amount, $row->status->documentId);
                return true;
            }
            $c++;
        }
        return false;
    }

    public function addTransaction(Row $row, ImportLineModel $line)
    {
        global $Refs;

        $sqlDate = $this->sqlDate($row->data[$this->dateColumn]);
        $faDate = sql2date($sqlDate);

        $cart = new \purch_order;
        $cart->trans_type = ST_SUPPINVOICE;
        $cart->order_no = 0;
        $cart->due_date = $faDate;
        $cart->orig_order_date = $faDate;
        $cart->supp_ref = $row->data[$this->dateColumn];
        $cart->supplier_id = $line->partyId;
        $cart->reference = $Refs->get_next(
            $cart->trans_type, null,
            ['supplier_id' => $cart->supplier_id, 'date' => $faDate]
        );

        $amount = (float)$row->data[$this->amountColumn];
        $paymentAmount = -$amount; // Positive amounts needed for payments
        $c = count($cart->line_items);
        if (!$line->docCode) {
            throw new \Exception(sprintf("No Document Code for supplier in line '%s'", $line->partyMatch));
        }
        $cart->add_to_order($c, $line->docCode, 1, null, $paymentAmount, null, '', 0, 0);
        $transNumber = add_direct_supp_trans($cart);

        // Payment
        $paymentRef = $Refs->get_next(
            ST_SUPPAYMENT, null,
            null // Review CP 2022-02
        );
        $paymentNumber = write_supp_payment(
            0, $line->partyId, $this->fileType->bankId, $faDate, $paymentRef,
            $paymentAmount, 0.0, $line->partyMatch, 0, 0
        );

        // Allocation
        $allocation = new \allocation(ST_SUPPAYMENT, $paymentNumber, $line->partyId);
        $allocation->date_ = $faDate;
        $allocation->amount = $amount;
        $allocation->person_type = PT_SUPPLIER;
        $allocation->person_id = $line->partyId;
        $allocation->add_item(
            ST_SUPPINVOICE, $transNumber, $faDate, $faDate,
            $paymentAmount, $paymentAmount, $paymentAmount, $paymentRef
        );
        $allocation->write();

        // Status
        $row->status->status = RowStatus::STATUS_NEW;
        $row->status->documentId = $paymentNumber;
        $row->status->documentType = ST_SUPPAYMENT;
        $row->status->link = 'purchasing/view/view_supp_payment.php?trans_no=' . $row->status->documentId;

        $this->importState->pushPartyAmount($sqlDate, $line->partyCode, $amount, $row->status->documentId);

    }

}

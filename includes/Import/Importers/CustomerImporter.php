<?php
namespace SGW_Import\Import\Importers;

use SGW_Import\Import\Row;
use SGW_Import\Import\RowStatus;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Model\TransactionModel;

class CustomerImporter extends Importer
{
    public function transactionExists(Row $row, ImportLineModel $line)
    {
        $bankId = $this->fileType->bankId;
        $sqlDate = $this->sqlDate($row->data[$this->dateColumn]);
        $transactions = TransactionModel::fromBankTransaction($sqlDate, $row->data[$this->amountColumn], $bankId, ST_CUSTPAYMENT);
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
            $row->status->link = 'sales/view/view_receipt.php?trans_no=' . $row->status->documentId;
            // http://localhost:8000/sales/view/view_receipt.php?trans_no=617&trans_type=12
            return true;
        } else {
            // Try harder
            $invoiceRef = $this->docReference($row, $line);
            $transactions = null;
            if ($invoiceRef) {
                $transactions = TransactionModel::fromBankPaymentAndInvoice($sqlDate, $row->data[$this->amountColumn], $bankId, $invoiceRef);
            } else {
                $transactions = TransactionModel::fromBankPaymentAndPartyId($sqlDate, $row->data[$this->amountColumn], $bankId, $line->partyId);
            }
            $t2 = [];
            $c2 = 0;
            foreach ($transactions as $transaction) {
                $t2[] = clone $transaction;
                $c2++;
            }
            if ($c2 == 1) {
                $row->status->status = RowStatus::STATUS_EXISTING;
                $row->status->documentType = $t2[0]->type;
                $row->status->documentId = $t2[0]->number;
                $row->status->link = 'sales/view/view_receipt.php?trans_no=' . $row->status->documentId;
                return true;
            } else {
                // TODO This is uncertain, likely an unallocated customer payment
                $row->status->status = RowStatus::STATUS_MANUAL;
            }
        }
        return false;
    }

    public function addTransaction(Row $row, ImportLineModel $line)
    {
        global $Refs;

    }

}

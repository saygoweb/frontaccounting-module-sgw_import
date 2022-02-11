<?php
namespace SGW_Import\Import\Importers;

use SGW_Import\Import\Row;
use SGW_Import\Import\RowStatus;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Model\TransactionModel;

class TransferImporter extends Importer
{
    public function transactionExists(Row $row, ImportLineModel $line)
    {
        $bankId = $this->fileType->bankId;
        $sqlDate = $this->sqlDate($row->data[$this->dateColumn]);
        $transactions = TransactionModel::fromBankTransfer($sqlDate, $row->data[$this->amountColumn], $line->partyId, $bankId);
        $c = 0;
        $t = [];
        foreach ($transactions as $transaction) {
            $t[] = clone $transaction;
            $c++;
        }
        // Status
        if ($c == 2) {
            $row->status->status = RowStatus::STATUS_EXISTING;
            $row->status->documentType = $t[0]->type;
            $row->status->documentId = $t[0]->number;
            $row->status->link = 'gl/view/bank_transfer_view.php?trans_no=' . $row->status->documentId;
        }

        if ($c != 0 && $c != 2) {
            throw new \Exception("Unsupported transfer with $c records");
        }

        return $c == 2;
    }

    public function addTransaction(Row $row, ImportLineModel $line)
    {
        global $Refs;

        $amount = (float)$row->data[$this->amountColumn];
        if ($amount < 0) {
            // Going out of this account
            $fromAccountId = $this->fileType->bankId;
            $toAccountId = $line->partyId;
        } else {
            $fromAccountId = $line->partyId;
            $toAccountId = $this->fileType->bankId;
        }
        $sqlDate = $this->sqlDate($row->data[$this->dateColumn]);
        $faDate = sql2date($sqlDate);

        $ref = $Refs->get_next(ST_BANKTRANSFER);

        $trans_no = add_bank_transfer($fromAccountId, $toAccountId, $faDate, abs($amount), $ref, $line->partyCode, 0, 0);

        $row->status->status = RowStatus::STATUS_NEW;
        $row->status->documentId = $trans_no;
        $row->status->documentType = ST_BANKTRANSFER;
        $row->status->link = 'gl/view/bank_transfer_view.php?trans_no=' . $row->status->documentId;

    }


}

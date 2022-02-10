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
        $transactions = TransactionModel::fromBankTransfer($row->data[$this->dateColumn], $row->data[$this->amountColumn], $line->partyId, $bankId);
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

    }


}

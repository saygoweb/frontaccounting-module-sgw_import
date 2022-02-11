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
        } 
        // Try with the invoice ref
        $invoiceRef = $this->docReference($row, $line);
        if ($invoiceRef) {
            $transactions = TransactionModel::fromBankPaymentAndInvoice($sqlDate, $row->data[$this->amountColumn], $bankId, $invoiceRef);
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
            }
        }
        // Try for a single transaction allocated to this party
        $transactions = TransactionModel::fromBankPaymentAndPartyId($sqlDate, $row->data[$this->amountColumn], $bankId, $line->partyId);
        $t3 = [];
        $c3 = 0;
        foreach ($transactions as $transaction) {
            $t3[] = clone $transaction;
            $c3++;
        }
        if ($c3 == 1) {
            $row->status->status = RowStatus::STATUS_EXISTING;
            $row->status->documentType = $t3[0]->type;
            $row->status->documentId = $t3[0]->number;
            $row->status->link = 'sales/view/view_receipt.php?trans_no=' . $row->status->documentId;
            return true;
        }
        if ($c == 0) {
            // There was no payment at all on this date so it is TODO
            $row->status->status = RowStatus::STATUS_TODO;
            return false;
        }
        // c || c2 || c3 > 1 This is uncertain, likely an unallocated customer payment
        $row->status->status = RowStatus::STATUS_MANUAL;
        return false;
    }

    public function addTransaction(Row $row, ImportLineModel $line)
    {
        global $Refs;

        $sqlDate = $this->sqlDate($row->data[$this->dateColumn]);
        $faDate = sql2date($sqlDate);
        $amount = (float)$row->data[$this->amountColumn];

        // Either create or find the invoice.
        $invoiceTransactionNumber = null;
        if ($line->docType == ImportLineModel::DT_CUSTOMER_INVOICE) {
            // Create a new invoice
            $cart = new \Cart(ST_SALESINVOICE);
            $cart->order_no = 0;
            $cart->document_date = $faDate;
            $cart->due_date = $faDate;
            $cart->cust_ref = $sqlDate;
            $cart->customer_id = $line->partyId;
            $cart->reference = $Refs->get_next(
                $cart->trans_type, null,
                ['customer_id' => $cart->customer_id, 'date' => $faDate]
            );
    
            $c = count($cart->line_items);
            $cart->add_to_cart(
                $c, $line->docCode, 1, $amount,
                0, 0, null, 0, 0, 0
            );
            $invoiceTransactionNumber = $cart->write(1);
        } else {
            // Try to find the invoice
            $invoices = TransactionModel::fromPartyIdAndUnallocatedInvoices($line->partyId);
            $invoiceRef = $this->docReference($row, $line);
            $found = null;
            $possibles = [];
            foreach ($invoices as $invoice) {
                if ($invoiceRef && $invoice->ref == $invoiceRef) {
                    $found = clone $invoice;
                    break;
                }
                $possibles[] = clone $invoice;
            }
            if (!$found) {
                if (count($possibles) == 1 && $possibles[0]->amount == $amount) {
                    $found = $possibles[0];
                }
            }
            if ($found) {
                $invoiceTransactionNumber = $found->number;
            }
        }

        // Payment
        $paymentRef = $Refs->get_next(
            ST_CUSTPAYMENT, null,
            null // Review CP 2022-02
        );
        $branch = get_default_branch($line->partyId);
        $branchId = $branch['branch_code'];
        $paymentNumber = write_customer_payment(
            0, $line->partyId, $branchId, $this->fileType->bankId, $faDate, $paymentRef,
            $amount, 0, $line->partyMatch, 0, 0, 0
        );

        if ($invoiceTransactionNumber) {
            // We can do the allocation
            $allocation = new \allocation(ST_CUSTPAYMENT, $paymentNumber, $line->partyId);
            $allocation->date_ = $faDate;
            $allocation->amount = $amount;
            $allocation->person_type = PT_CUSTOMER;
            $allocation->person_id = $line->partyId;
            $allocation->add_item(
                ST_SALESINVOICE, $invoiceTransactionNumber, $faDate, $faDate,
                $amount, $amount, $amount, $paymentRef
            );
            $allocation->write();
        }

        // Status
        $row->status->status = RowStatus::STATUS_NEW;
        $row->status->documentId = $paymentNumber;
        $row->status->documentType = ST_CUSTPAYMENT;
        $row->status->link = 'sales/view/view_receipt.php?trans_no=' . $row->status->documentId;
    }

}

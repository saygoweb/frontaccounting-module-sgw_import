<?php

namespace SGW_Import\Tests\Integration;

use SGW_Import\Import\Importers\Importer;
use SGW_Import\Import\Importers\ImportState;
use SGW_Import\Import\Row;
use SGW_Import\Import\RowStatus;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Tests\Support\DemoCustomers;

/**
 * Each importer against FrontAccounting on the demo company: a bank line it
 * has not seen is not there, addTransaction() writes FrontAccounting's
 * document for it, and a later import (a fresh ImportState, as a new page
 * load has) finds that document and does not write another.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ImportersTest extends FaTestCase
{
    private const CUSTOMER = DemoCustomers::CASH;
    /** Demo supplier 1, Dino Saurius Inc.: USD. */
    private const SUPPLIER = 1;
    /** Demo quick entry 2, Phone: a bank payment. */
    private const QUICK_PAYMENT = 2;
    /** Demo quick entry 3, Cash Sales: a bank deposit. */
    private const QUICK_DEPOSIT = 3;
    /** Demo item 301, Support: a service. */
    private const SERVICE = '301';

    private ImportFileTypeModel $fileType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fileType = $this->fileType();
    }

    public function testAQuickPaymentIsWrittenOnceAsABankPayment(): void
    {
        $line = $this->line(ImportLineModel::PT_QUICK_PAYMENT, self::QUICK_PAYMENT, 'Phone Co');
        $row = $this->row($this->date(10), '-43.21', 'Phone Co');

        $this->assertImportedOnce($row, $line, ST_BANKPAYMENT, 'gl/view/gl_payment_view.php?trans_no=');

        $this->assertBankTrans(ST_BANKPAYMENT, $row, -43.21);
        $this->assertGlBalanced(ST_BANKPAYMENT, (int) $row->status->documentId);
    }

    public function testAQuickDepositIsWrittenOnceAsABankDeposit(): void
    {
        $line = $this->line(ImportLineModel::PT_QUICK_DEPOSIT, self::QUICK_DEPOSIT, 'Market stall');
        $row = $this->row($this->date(11), '87.65', 'Market stall');

        $this->assertImportedOnce($row, $line, ST_BANKDEPOSIT, 'gl/view/gl_deposit_view.php?trans_no=');

        $this->assertBankTrans(ST_BANKDEPOSIT, $row, 87.65);
        $this->assertGlBalanced(ST_BANKDEPOSIT, (int) $row->status->documentId);
    }

    public function testATransferOutIsWrittenOnceFromThisAccountToTheLinesAccount(): void
    {
        $line = $this->line(ImportLineModel::PT_TRANSFER, self::PETTY_CASH, 'To petty cash');
        $row = $this->row($this->date(12), '-65.43', 'To petty cash');

        $this->assertImportedOnce($row, $line, ST_BANKTRANSFER, 'gl/view/bank_transfer_view.php?trans_no=');

        $amounts = $this->pdo()->query(
            'SELECT bank_act, amount FROM 0_bank_trans WHERE type = ' . ST_BANKTRANSFER
            . ' AND trans_no = ' . (int) $row->status->documentId . ' ORDER BY bank_act'
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        $this->assertEqualsWithDelta(
            [self::BANK => -65.43, self::PETTY_CASH => 65.43],
            array_map('floatval', $amounts),
            0.001
        );
    }

    public function testATransferInIsWrittenFromTheLinesAccountToThisOne(): void
    {
        $line = $this->line(ImportLineModel::PT_TRANSFER, self::PETTY_CASH, 'From petty cash');
        $row = $this->row($this->date(13), '12.34', 'From petty cash');

        $this->assertImportedOnce($row, $line, ST_BANKTRANSFER, 'gl/view/bank_transfer_view.php?trans_no=');

        $amounts = $this->pdo()->query(
            'SELECT bank_act, amount FROM 0_bank_trans WHERE type = ' . ST_BANKTRANSFER
            . ' AND trans_no = ' . (int) $row->status->documentId . ' ORDER BY bank_act'
        )->fetchAll(\PDO::FETCH_KEY_PAIR);
        $this->assertEqualsWithDelta(
            [self::BANK => 12.34, self::PETTY_CASH => -12.34],
            array_map('floatval', $amounts),
            0.001
        );
    }

    public function testASupplierLineIsWrittenOnceAsAnInvoicePaidAndAllocated(): void
    {
        $line = $this->line(ImportLineModel::PT_SUPPLIER, self::SUPPLIER, 'Dino Saurius', [
            'docType' => ImportLineModel::DT_SUPPLIER_INVOICE,
            'docCode' => self::SERVICE,
        ]);
        $row = $this->row($this->date(14), '-54.32', 'Dino Saurius');

        $this->assertImportedOnce($row, $line, ST_SUPPAYMENT, 'purchasing/view/view_supp_payment.php?trans_no=');

        $payment = $this->pdo()->query(
            'SELECT ov_amount, alloc FROM 0_supp_trans WHERE type = ' . ST_SUPPAYMENT
            . ' AND trans_no = ' . (int) $row->status->documentId
        )->fetch(\PDO::FETCH_ASSOC);
        $this->assertEqualsWithDelta(-54.32, (float) $payment['ov_amount'], 0.001);
        $this->assertEqualsWithDelta(54.32, (float) $payment['alloc'], 0.001, 'allocated to the invoice');
        // The invoice's one line is priced at the amount paid; the supplier's
        // tax (demo tax group 1, 5%) goes on top, and stays unpaid: issue #17.
        $invoice = $this->pdo()->query(
            'SELECT ov_amount, alloc FROM 0_supp_trans WHERE type = ' . ST_SUPPINVOICE
            . ' AND supplier_id = ' . self::SUPPLIER . " AND tran_date = '" . $this->date(14) . "'"
        )->fetch(\PDO::FETCH_NUM);
        $this->assertEqualsWithDelta(54.32, (float) $invoice[0], 0.001, 'the invoice is the amount paid, before tax');
        $this->assertEqualsWithDelta(54.32, (float) $invoice[1], 0.001, 'and that is allocated');
        $this->assertGlBalanced(ST_SUPPAYMENT, (int) $row->status->documentId);
    }

    public function testASupplierLineWithoutAnItemIsRefused(): void
    {
        $line = $this->line(ImportLineModel::PT_SUPPLIER, self::SUPPLIER, 'Dino Saurius');

        $this->expectExceptionMessage("No Document Code for supplier in line 'Dino Saurius'");
        $this->importer($line)->addTransaction($this->row($this->date(15), '-1.00', 'Dino Saurius'), $line);
    }

    public function testACustomerPaymentIsWrittenOnce(): void
    {
        $line = $this->line(ImportLineModel::PT_CUSTOMER, self::CUSTOMER, 'Donald Easter');
        $row = $this->row($this->date(16), '76.54', 'Donald Easter');

        $this->assertImportedOnce($row, $line, ST_CUSTPAYMENT, 'sales/view/view_receipt.php?trans_no=');

        $this->assertBankTrans(ST_CUSTPAYMENT, $row, 76.54);
        $this->assertGlBalanced(ST_CUSTPAYMENT, (int) $row->status->documentId);
    }

    public function testACustomerLineCanInvoiceThePaymentAndAllocateIt(): void
    {
        $customer = $this->creditCustomer();
        $line = $this->line(ImportLineModel::PT_CUSTOMER, $customer, 'On credit', [
            'docType' => ImportLineModel::DT_CUSTOMER_INVOICE,
            'docCode' => self::SERVICE,
        ]);
        $row = $this->row($this->date(17), '98.76', 'On credit');

        $this->importer($line)->addTransaction($row, $line);

        $this->assertSame(RowStatus::STATUS_NEW, $row->status->status);
        $payment = $this->debtorTrans(ST_CUSTPAYMENT, (int) $row->status->documentId);
        $this->assertEqualsWithDelta(98.76, (float) $payment['ov_amount'], 0.001);
        $this->assertEqualsWithDelta(98.76, (float) $payment['alloc'], 0.001, 'allocated to the new invoice');
        $invoice = $this->pdo()->query(
            'SELECT trans_no, alloc FROM 0_debtor_trans WHERE type = ' . ST_SALESINVOICE
            . " AND debtor_no = $customer AND tran_date = '" . $this->date(17) . "'"
        )->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($invoice, 'an invoice dated the bank line');
        $this->assertEqualsWithDelta(98.76, (float) $invoice['alloc'], 0.001);
    }

    public function testACustomerPaymentIsAllocatedToTheInvoiceItsLineNames(): void
    {
        $customer = $this->creditCustomer();
        $line = $this->line(ImportLineModel::PT_CUSTOMER, $customer, 'On credit', [
            'docMatch' => '/INV-[0-9]+/',
        ]);
        $invoice = $this->invoice($customer, $this->date(18), 'INV-' . random_int(100000, 999999));
        $row = $this->row($this->date(19), (string) $invoice['total'], 'On credit', 'Paying ' . $invoice['reference']);

        $this->assertImportedOnce($row, $line, ST_CUSTPAYMENT, 'sales/view/view_receipt.php?trans_no=');

        $this->assertEqualsWithDelta(
            $invoice['total'],
            (float) $this->debtorTrans(ST_SALESINVOICE, $invoice['trans_no'])['alloc'],
            0.001,
            'the invoice its memo names is paid'
        );
    }

    public function testAPaymentForTheInvoiceItNamesLeavesAnOlderOpenInvoiceAlone(): void
    {
        $customer = $this->creditCustomer();
        $line = $this->line(ImportLineModel::PT_CUSTOMER, $customer, 'On credit', [
            'docMatch' => '/INV-[0-9]+/',
        ]);
        $older = $this->invoice($customer, $this->date(21), 'INV-' . random_int(100000, 999999));
        $named = $this->invoice($customer, $this->date(22), 'INV-' . random_int(100000, 999999));
        $row = $this->row($this->date(23), (string) $named['total'], 'On credit', 'Paying ' . $named['reference']);

        $this->importer($line)->addTransaction($row, $line);

        $namedAlloc = (float) $this->debtorTrans(ST_SALESINVOICE, $named['trans_no'])['alloc'];
        $this->assertEqualsWithDelta($named['total'], $namedAlloc, 0.001);
        $olderAlloc = (float) $this->debtorTrans(ST_SALESINVOICE, $older['trans_no'])['alloc'];
        $this->assertEqualsWithDelta(0.0, $olderAlloc, 0.001);
        $this->assertEqualsWithDelta(
            $named['total'],
            (float) $this->debtorTrans(ST_CUSTPAYMENT, (int) $row->status->documentId)['alloc'],
            0.001,
            'the payment allocated once'
        );
    }

    public function testAnotherPaymentOfTheSameAmountThatDayIsNotTakenForThisOne(): void
    {
        $line = $this->line(ImportLineModel::PT_CUSTOMER, self::CUSTOMER, 'Donald Easter');
        $first = $this->row($this->date(20), '11.11', 'Donald Easter');
        $this->importer($line)->addTransaction($first, $line);

        $state = new ImportState();
        $importer = Importer::fromPartyType($line->partyType, $this->fileType, $state);
        $this->assertTrue($importer->transactionExists($this->row($this->date(20), '11.11', 'Donald Easter'), $line));
        $this->assertFalse(
            $importer->transactionExists($this->row($this->date(20), '11.11', 'Donald Easter'), $line),
            'the second line of the same amount is a second payment'
        );
    }

    /**
     * $row is not there; addTransaction() writes it as a $type; a new import
     * finds that document, and the same importer's state then counts it used.
     */
    private function assertImportedOnce(Row $row, ImportLineModel $line, int $type, string $link): void
    {
        $this->assertFalse($this->importer($line)->transactionExists($row, $line), 'not imported yet');

        $this->importer($line)->addTransaction($row, $line);

        $this->assertSame(RowStatus::STATUS_NEW, $row->status->status);
        $this->assertSame($type, (int) $row->status->documentType);
        $this->assertGreaterThan(0, (int) $row->status->documentId);
        $this->assertSame($link . $row->status->documentId, $row->status->link);

        $again = $this->row(...$row->data);
        $this->assertTrue($this->importer($line)->transactionExists($again, $line), 'found by a later import');
        $this->assertSame(RowStatus::STATUS_EXISTING, $again->status->status);
        $this->assertSame($type, (int) $again->status->documentType);
        $this->assertSame((string) $row->status->documentId, (string) $again->status->documentId);
        $this->assertSame($row->status->link, $again->status->link);
    }

    private function importer(ImportLineModel $line): Importer
    {
        return Importer::fromPartyType($line->partyType, $this->fileType, new ImportState());
    }

    private function assertBankTrans(int $type, Row $row, float $amount): void
    {
        $bank = $this->pdo()->query(
            "SELECT bank_act, trans_date, amount FROM 0_bank_trans WHERE type = $type AND trans_no = "
            . (int) $row->status->documentId
        )->fetchAll(\PDO::FETCH_ASSOC);
        $this->assertCount(1, $bank);
        $this->assertSame(self::BANK, (int) $bank[0]['bank_act']);
        $this->assertSame($row->data[0], $bank[0]['trans_date']);
        $this->assertEqualsWithDelta($amount, (float) $bank[0]['amount'], 0.001);
    }

    private function assertGlBalanced(int $type, int $transNo): void
    {
        $gl = $this->pdo()->query(
            "SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM 0_gl_trans WHERE type = $type AND type_no = $transNo"
        )->fetch(\PDO::FETCH_NUM);
        $this->assertGreaterThan(0, (int) $gl[0], 'GL entries written');
        $this->assertEqualsWithDelta(0.0, (float) $gl[1], 0.001, 'GL balanced');
    }

    /**
     * @return array<string, string>
     */
    private function debtorTrans(int $type, int $transNo): array
    {
        $row = $this->pdo()->query(
            "SELECT * FROM 0_debtor_trans WHERE type = $type AND trans_no = $transNo"
        )->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row, "debtor_trans $type/$transNo");

        return $row;
    }

    private function creditCustomer(): int
    {
        return DemoCustomers::onCredit($this->pdo());
    }

    /**
     * A direct invoice for one of item SERVICE, written by FrontAccounting's
     * own sales cart, with $reference.
     *
     * @return array{trans_no: int, reference: string, total: float}
     */
    private function invoice(int $customerId, string $sqlDate, string $reference): array
    {
        $date = sql2date($sqlDate);
        $cart = new \Cart(ST_SALESINVOICE);
        $cart->order_no = 0;
        $cart->document_date = $date;
        get_customer_details_to_order($cart, $customerId, get_default_branch($customerId)['branch_code']);
        $cart->reference = $reference;
        $cart->add_to_cart(0, self::SERVICE, 1, 20.00, 0, 0, null, 0, 0, 0);
        $transNo = (int) $cart->write(1);
        $total = $this->debtorTrans(ST_SALESINVOICE, $transNo);

        return [
            'trans_no' => $transNo,
            'reference' => $reference,
            'total' => round((float) $total['ov_amount'] + (float) $total['ov_gst'], 2),
        ];
    }
}

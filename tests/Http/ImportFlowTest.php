<?php

namespace SGW_Import\Tests\Http;

use PHPUnit\Framework\TestCase;
use SGW_Import\Tests\Support\DemoCustomers;
use SGW_Import\Tests\Support\FaRows;
use SGW_Import\Tests\Support\UploadFolder;

/**
 * sgw_import's pages in Apache, as a user works through them: upload a bank
 * file, configure its file type, add a line for a payee and make it a quick
 * payment, import, import again, and delete the file. Rows written are
 * removed afterwards (FaRows), and the uploaded file.
 */
class ImportFlowTest extends TestCase
{
    /** Demo bank account 1: Current account. */
    private const BANK = 1;
    /** Demo quick entry 2: Phone, a bank payment. */
    private const QUICK_PAYMENT = 2;

    private FaBrowser $browser;

    private \PDO $pdo;

    private FaRows $rows;

    private UploadFolder $folder;

    private string $name;

    protected function setUp(): void
    {
        $url = getenv('FA_URL');
        if (!$url || !getenv('FA_DB_HOST')) {
            $this->markTestSkipped('FA_URL and FA_DB_*: run in the FrontAccounting CI image.');
        }
        $this->pdo = new \PDO(
            'mysql:host=' . getenv('FA_DB_HOST') . ';dbname=' . getenv('FA_DB_NAME'),
            (string) getenv('FA_DB_USER'),
            (string) getenv('FA_DB_PASSWORD'),
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
        $this->rows = FaRows::mark($this->pdo);
        $this->folder = new UploadFolder();
        $this->name = $this->folder->uniqueName();
        $this->browser = new FaBrowser($url);
        $this->browser->signIn();
    }

    protected function tearDown(): void
    {
        if (isset($this->rows)) {
            $this->rows->purge();
        }
        if (isset($this->folder)) {
            if (is_file($this->folder->path($this->name))) {
                unlink($this->folder->path($this->name));
            }
            $this->folder->remove();
        }
    }

    public function testABankFileIsUploadedConfiguredImportedOnceAndDeleted(): void
    {
        $date = (string) $this->pdo->query(
            "SELECT DATE_ADD(begin, INTERVAL 30 DAY) FROM 0_fiscal_year WHERE CURDATE() BETWEEN begin AND end"
        )->fetchColumn();

        // Upload: the file is recorded, and the bank account's file type
        // takes its columns from the header.
        $page = $this->upload("Date,Amount,Payee,Memo\n$date,-43.21,Phone Co,March bill\n");
        $this->assertPageOk($page);
        $this->assertStringContainsString($this->name, $page);
        $fileId = (int) $this->pdo->query(
            "SELECT id FROM 0_import_file WHERE file_name = " . $this->pdo->quote($this->name)
        )->fetchColumn();
        $this->assertGreaterThan(0, $fileId);
        $fileType = $this->pdo->query('SELECT * FROM 0_import_file_type WHERE bank_id = ' . self::BANK)
            ->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('Date,Amount,Payee,Memo', $fileType['columns']);

        // Configure Import Transactions: which columns are the date and amount.
        $this->assertPageOk($this->browser->post('modules/sgw_import/admin/admin_file_type.php', [
            'id' => $fileType['id'],
            'hide' => 'Memo',
            'date_field' => 0,
            'date_format' => 'yyyymmdd',
            'amount_field' => 1,
            'UPDATE_ITEM' => 'Update',
        ]));
        $fileType = $this->pdo->query('SELECT * FROM 0_import_file_type WHERE bank_id = ' . self::BANK)
            ->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(
            ['Date', 'Amount', 'Memo'],
            [$fileType['date_field'], $fileType['amount_field'], $fileType['hide']]
        );

        // The file's rows, and Add Line for the payee of row 1.
        $page = $this->browser->get("modules/sgw_import/import_file.php?id=$fileId");
        $this->assertPageOk($page);
        $this->assertStringContainsString('Phone Co', $page);
        $this->assertPageOk($this->browser->post(
            "modules/sgw_import/import_file.php?id=$fileId",
            ['id' => $fileId, 'a_1' => 'Add Line']
        ));
        $lineId = (int) $this->pdo->query(
            "SELECT id FROM 0_import_line WHERE bank_id = " . self::BANK . " AND party_match = 'Phone Co'"
        )->fetchColumn();
        $this->assertGreaterThan(0, $lineId);

        // Edit Line: a quick payment, Phone.
        $page = $this->browser->get("modules/sgw_import/import_line.php?id=$lineId");
        $this->assertPageOk($page);
        $this->assertPageOk($this->browser->post("modules/sgw_import/import_line.php?id=$lineId", [
            'id' => $lineId,
            'bank_id' => self::BANK,
            'party_match' => 'Phone Co',
            'party_field' => 2,
            'party_type' => 'quick',
            'party_id' => self::QUICK_PAYMENT,
            'doc_field' => 3,
            'doc_match' => '',
            'doc_type' => 'none',
            'stock_id' => '',
            'UPDATE_ITEM' => 'Update',
        ]));
        $line = $this->pdo->query("SELECT * FROM 0_import_line WHERE id = $lineId")->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame(
            ['quick', (string) self::QUICK_PAYMENT, 'Phone'],
            [$line['party_type'], $line['party_id'], $line['party_code']]
        );

        // Import row 1: a new bank payment.
        $page = $this->import($fileId);
        $this->assertPageOk($page);
        $this->assertStringContainsString('gl/view/gl_payment_view.php?trans_no=', $page);
        $this->assertStringContainsString('>New<', $page);
        $this->assertSame(1, $this->payments($date));

        // Import it again: it exists, and nothing more is written.
        $page = $this->import($fileId);
        $this->assertPageOk($page);
        $this->assertStringContainsString('>Exists<', $page);
        $this->assertSame(1, $this->payments($date));

        // Delete the file: its record and its upload go.
        $this->assertPageOk($this->browser->post('modules/sgw_import/import_files.php', ["d_$fileId" => 'Delete']));
        $this->assertFalse($this->pdo->query("SELECT id FROM 0_import_file WHERE id = $fileId")->fetchColumn());
        clearstatcache();
        $this->assertFileDoesNotExist($this->folder->path($this->name));
    }

    public function testEveryKindOfLineImportsThroughThePage(): void
    {
        $date = (string) $this->pdo->query(
            "SELECT DATE_ADD(begin, INTERVAL 40 DAY) FROM 0_fiscal_year WHERE CURDATE() BETWEEN begin AND end"
        )->fetchColumn();
        $this->upload(
            "Date,Amount,Payee,Memo\n"
            . "$date,-54.32,Dino Saurius,Invoice\n"
            . "$date,76.54,Donald Easter,Payment\n"
            . "$date,-65.43,To petty cash,Float\n"
            . "$date,87.65,Market stall,Takings\n"
            . "$date,98.76,On credit,Support\n"
        );
        $customer = DemoCustomers::onCredit($this->pdo);
        $fileId = (int) $this->pdo->query(
            "SELECT id FROM 0_import_file WHERE file_name = " . $this->pdo->quote($this->name)
        )->fetchColumn();
        $this->pdo->exec(
            "UPDATE 0_import_file_type SET date_field = 'Date', amount_field = 'Amount', date_format = 'yyyymmdd'
             WHERE bank_id = " . self::BANK
        );
        $this->pdo->exec(
            "INSERT INTO 0_import_line (bank_id, party_field, party_match, party_code, party_type, party_id,
                doc_field, doc_match, doc_type, doc_code) VALUES
             (1, 'Payee', 'Dino Saurius', 'Dino Saurius', 'supplier', 1, 'Memo', '', 'supplier_invoice', '301'),
             (1, 'Payee', 'Donald Easter', 'Donald Easter', 'customer', 1, 'Memo', '', 'none', ''),
             (1, 'Payee', 'To petty cash', 'Petty Cash account', 'transfer', 2, 'Memo', '', 'none', ''),
             (1, 'Payee', 'Market stall', 'Cash Sales', 'quick_deposit', 3, 'Memo', '', 'none', ''),
             (1, 'Payee', 'On credit', 'On credit', 'customer', $customer, 'Memo', '', 'customer_invoice', '301')"
        );

        $page = $this->browser->post("modules/sgw_import/import_file.php?id=$fileId", [
            'id' => $fileId,
            's_1' => 1,
            's_2' => 1,
            's_3' => 1,
            's_4' => 1,
            's_5' => 1,
            'submit_import' => 'Import Data',
        ]);

        $this->assertPageOk($page);
        $this->assertSame(5, substr_count($page, '>New<'), 'five lines imported');
        foreach (
            [
                'purchasing/view/view_supp_payment.php',
                'sales/view/view_receipt.php',
                'gl/view/bank_transfer_view.php',
                'gl/view/gl_deposit_view.php',
            ] as $view
        ) {
            $this->assertStringContainsString($view, $page);
        }
    }

    public function testConfigureImportTransactionsListsTheBankAccountsLines(): void
    {
        $this->upload("Date,Amount,Payee\n");
        $this->pdo->exec(
            "INSERT INTO 0_import_line (bank_id, party_field, party_match, party_code, party_type, doc_type, party_id)
             VALUES (" . self::BANK . ", 'Payee', 'Dino Saurius', 'Dino Saurius', 'supplier', 'none', 1)"
        );

        $page = $this->browser->get('modules/sgw_import/admin/admin_file_type.php');

        $this->assertPageOk($page);
        $this->assertStringContainsString('Dino Saurius', $page);
        $this->assertStringContainsString('Date,Amount,Payee', $page);
    }

    public function testEditLineKeepsABlankDocumentField(): void
    {
        // A trailing comma, as in BNZ's exports, makes a blank last column.
        $this->upload("Date,Amount,Payee,\n");
        $this->pdo->exec(
            "INSERT INTO 0_import_line (bank_id, party_field, party_match, party_code, party_type, party_id,
                doc_field, doc_match, doc_type, doc_code)
             VALUES (" . self::BANK . ", 'Payee', 'Phone Co', 'Phone Co', 'supplier', 1, '', '', 'none', '')"
        );
        $lineId = (int) $this->pdo->lastInsertId();

        $page = $this->browser->get("modules/sgw_import/import_line.php?id=$lineId");
        $this->assertPageOk($page);
        $this->assertMatchesRegularExpression(
            '#<select name="doc_field"[^>]*>\s*<option value="0">Date</option>#',
            $page
        );
        $this->assertStringContainsString('<option value="3" selected></option>', $page);

        $this->assertPageOk($this->browser->post("modules/sgw_import/import_line.php?id=$lineId", [
            'id' => $lineId,
            'bank_id' => self::BANK,
            'party_match' => 'Phone Co',
            'party_field' => 2,
            'party_type' => 'supplier',
            'party_id' => 1,
            'doc_field' => 3,
            'doc_match' => '',
            'doc_type' => 'none',
            'stock_id' => '',
            'UPDATE_ITEM' => 'Update',
        ]));
        $docField = $this->pdo->query("SELECT doc_field FROM 0_import_line WHERE id = $lineId")->fetchColumn();
        $this->assertSame('', $docField);
    }

    private function upload(string $csv): string
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tmp, $csv);
        try {
            return $this->browser->post('modules/sgw_import/import_files.php', [
                'bank_id' => self::BANK,
                'filename' => new \CURLFile($tmp, 'text/csv', $this->name),
                'upload' => 'Upload',
            ]);
        } finally {
            unlink($tmp);
        }
    }

    private function import(int $fileId): string
    {
        return $this->browser->post("modules/sgw_import/import_file.php?id=$fileId", [
            'id' => $fileId,
            's_1' => 1,
            'submit_import' => 'Import Data',
        ]);
    }

    private function payments(string $date): int
    {
        return (int) $this->pdo->query(
            'SELECT COUNT(*) FROM 0_bank_trans WHERE type = 1 AND bank_act = ' . self::BANK
            . " AND trans_date = '$date' AND amount = -43.21"
        )->fetchColumn();
    }

    /**
     * The page rendered to its end, without one of FrontAccounting's error
     * messages.
     */
    private function assertPageOk(string $page): void
    {
        $text = trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($page))));
        $this->assertTrue(stripos($page, '</html>') !== false, "the page stopped part-way: $text");
        $this->assertSame(0, preg_match('/class=["\']err_msg["\']/', $page), "an error on the page: $text");
    }
}

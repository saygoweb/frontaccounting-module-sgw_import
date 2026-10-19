<?php

namespace SGW_Import\Tests\Integration;

use Anorm\Anorm;
use FA\GraphQL\Config;
use FA\GraphQL\Fa\Bootstrap;
use FA\GraphQL\Fa\FaSession;
use PHPUnit\Framework\TestCase;
use SGW\DB;
use SGW_Import\Import\Row;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Tests\Support\FaRows;

/**
 * Tests that load FrontAccounting in-process, by the GraphQL module's Bootstrap
 * (sgw_import is deployed beside it), into company 0 of the demo dataset, with
 * Anorm connected and DB initialised as hooks_sgw_import does for a signed-in
 * user. Every row a test writes is removed in tearDown. Every subclass must carry
 *
 *     @runTestsInSeparateProcesses
 *     @preserveGlobalState disabled
 *
 * because FrontAccounting defines its functions, constants and globals once.
 */
abstract class FaTestCase extends TestCase
{
    /** Demo bank account 1: Current account, USD. */
    protected const BANK = 1;
    /** Demo bank account 2: Petty Cash account, USD. */
    protected const PETTY_CASH = 2;

    private ?\PDO $pdo = null;

    private ?FaRows $rows = null;

    protected function setUp(): void
    {
        if (!class_exists(Bootstrap::class)) {
            $this->markTestSkipped('No GraphQL module beside this one: run in the FrontAccounting CI image.');
        }
        $root = Bootstrap::defaultRoot();
        if (!is_file($root . '/config_db.php')) {
            $this->markTestSkipped('No FrontAccounting install at ' . $root . ': run in the FA CI image.');
        }
        (new FaSession(Config::fromArray(['secret' => str_repeat('x', 32)])))->openCompany(0);
        // The user FrontAccounting's audit trail records.
        $_SESSION['wa_current_user']->user = 1;
        $_SESSION['wa_current_user']->loginname = 'admin';
        // Its point of sale: a cash sale (demo customer 1) takes its location from it.
        $_SESSION['wa_current_user']->pos = 1;
        // Every area, as the CI image's test user has: FrontAccounting checks
        // access as it writes (a sales cart's dates, say).
        $_SESSION['wa_current_user']->role_set = array_map(function (array $area) {
            return $area[0];
        }, array_values($GLOBALS['security_areas']));
        // What sgw_import's pages include: the carts the importers fill, and
        // the functions that write them (ui.inc brings the rest of the gl, sales
        // and purchasing db layers in through includes/ui/ui_lists.inc).
        foreach (
            [
                'includes/ui.inc',
                'includes/ui/items_cart.inc',
                'includes/ui/allocation_cart.inc',
                'purchasing/includes/po_class.inc',
                'purchasing/includes/purchasing_db.inc',
                'sales/includes/ui/sales_order_ui.inc',
                'sales/includes/sales_db.inc',
                'gl/includes/gl_db.inc',
            ] as $file
        ) {
            Bootstrap::includeFa($file);
        }

        $c = $GLOBALS['db_connections'][0];
        Anorm::connect(Anorm::DEFAULT, "mysql:host={$c['host']};dbname={$c['dbname']}", $c['dbuser'], $c['dbpassword']);
        DB::init($c['tbpref']);

        $this->rows = FaRows::mark($this->pdo(), $c['tbpref']);
    }

    protected function tearDown(): void
    {
        if ($this->rows !== null) {
            $this->rows->purge();
        }
    }

    protected function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $c = $GLOBALS['db_connections'][0];
            $this->pdo = new \PDO("mysql:host={$c['host']};dbname={$c['dbname']}", $c['dbuser'], $c['dbpassword']);
            $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        }

        return $this->pdo;
    }

    /**
     * The bank account's file type, written: Date, Amount, Payee, Memo columns.
     */
    protected function fileType(
        int $bankId = self::BANK,
        string $dateFormat = ImportFileTypeModel::DTF_YYYYMMDD
    ): ImportFileTypeModel {
        $fileType = new ImportFileTypeModel();
        $fileType->bankId = $bankId;
        $fileType->columns = ['Date', 'Amount', 'Payee', 'Memo'];
        $fileType->hide = ['Memo'];
        $fileType->dateField = 'Date';
        $fileType->amountField = 'Amount';
        $fileType->dateFormat = $dateFormat;
        $fileType->write();

        return $fileType;
    }

    /**
     * An import line, written, matching $partyMatch in the Payee column.
     *
     * @param array<string, mixed> $properties
     */
    protected function line(string $partyType, $partyId, string $partyMatch, array $properties = []): ImportLineModel
    {
        $line = new ImportLineModel();
        $line->bankId = self::BANK;
        $line->partyField = 'Payee';
        $line->partyMatch = $partyMatch;
        $line->partyType = $partyType;
        $line->partyId = $partyId;
        $line->partyCode = $partyMatch;
        $line->docField = 'Memo';
        foreach ($properties as $name => $value) {
            $line->$name = $value;
        }
        $line->write();

        return $line;
    }

    protected function row(string $date, string $amount, string $payee, string $memo = ''): Row
    {
        $row = new Row();
        $row->data = [$date, $amount, $payee, $memo];
        $row->rowIndex = 1;

        return $row;
    }

    /**
     * A date in the current fiscal year, $days after its start, that no demo
     * transaction uses.
     */
    protected function date(int $days = 0): string
    {
        $begin = (string) $this->pdo()->query(
            'SELECT begin FROM 0_fiscal_year WHERE CURDATE() BETWEEN begin AND end'
        )->fetchColumn();

        return (new \DateTimeImmutable($begin))->modify("+$days day")->format('Y-m-d');
    }
}

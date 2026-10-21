<?php

use Anorm\Anorm;
use SGW\DB;

define('SS_SGW_IMPORT', 122 << 8);

include_once(__DIR__ . '/vendor/autoload.php');

class hooks_sgw_import extends hooks
{

    function __construct()
    {
        global $db_connections;
        $this->module_name = 'sgw_import';

        $company = user_company();
        $dbCredentials = $db_connections[$company];
        $host = $dbCredentials['host'];
        $database = $dbCredentials['dbname'];
		if ($_SESSION["wa_current_user"]->logged_in()) {
			Anorm::connect(Anorm::DEFAULT, "mysql:host=$host;dbname=$database", $dbCredentials['dbuser'], $dbCredentials['dbpassword']);
		}

        DB::init($dbCredentials['tbpref']);
    }

    /*
		Install additonal menu options provided by module
		*/
    function install_options($app)
    {
        global $path_to_root;

        switch ($app->id) {
            case 'GL':
                $app->add_rapp_function(
                    0,
                    _('Import &Transactions'),
                    "modules/sgw_import/import_files.php",
                    'SA_SGW_IMPORT_FILE',
                    MENU_TRANSACTION
                );
                $app->add_rapp_function(
                    2,
                    _('Configure Import Transactions'),
                    "modules/sgw_import/admin/admin_file_type.php",
                    'SA_SGW_IMPORT_FILE',
                    MENU_TRANSACTION
                );
                break;
        }
    }

    function install_access()
    {
        $security_sections[SS_SGW_IMPORT] =    _("SayGo Import");

        $security_areas['SA_SGW_IMPORT_FILE'] = array(
            SS_SGW_IMPORT | 1, _("Import File")
        );

        return array($security_areas, $security_sections);
    }

    /* This method is called on extension activation for company.   */
    function activate_extension($company, $check_only = true)
    {
        global $db_connections;

        if (!$check_only) {
            $this->refresh_own_version();
        }

        $updates = array(
            // 'update_1.0.sql' => array('sales_recurring')
        );

        return $this->update_databases($company, $updates, $check_only);
    }

    /*
        Stock FrontAccounting reads modules/<id>/_init/config (written by the
        module's build, docker/ci/fa-module) only when the extension is first
        installed. Refresh the stored version on activation so an upgrade shows
        its version on Install/Activate Extensions.
    */
    function refresh_own_version()
    {
        global $path_to_root;

        $file = $path_to_root . '/modules/' . $this->module_name . '/_init/config';
        if (!is_file($file)) {
            return;
        }
        $version = null;
        foreach (file($file) as $line) {
            if (preg_match('/^Version:\s*(\S+)/', $line, $m)) {
                $version = $m[1];
            }
        }
        if ($version === null) {
            return;
        }
        $exts = get_company_extensions();
        foreach ($exts as $i => $ext) {
            if (@$ext['package'] === $this->module_name && @$ext['type'] === 'extension') {
                if (@$ext['version'] !== $version) {
                    $exts[$i]['version'] = $version;
                    update_extensions($exts);
                }
                return;
            }
        }
    }

    private function remove_menu_item(&$items, $offset)
    {
        array_splice($items, $offset, 1);
    }
}

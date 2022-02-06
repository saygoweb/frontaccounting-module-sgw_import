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
        Anorm::connect(Anorm::DEFAULT, "mysql:host=$host;dbname=$database", $dbCredentials['dbuser'], $dbCredentials['dbpassword']);

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

        $updates = array(
            'update_1.0.sql' => array('sales_recurring')
        );

        return $this->update_databases($company, $updates, $check_only);
    }

    private function remove_menu_item(&$items, $offset)
    {
        array_splice($items, $offset, 1);
    }
}

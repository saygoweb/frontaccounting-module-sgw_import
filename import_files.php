<?php

/**********************************************************************
	Copyright (C) Arketec Ltd
	Released under the terms of the GNU General Public License, GPL, 
	as published by the Free Software Foundation, either version 3 
	of the License, or (at your option) any later version.
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
	See the License here <http://www.gnu.org/licenses/gpl-3.0.html>.
 ***********************************************************************/

use SGW_Import\Controller\ImportFiles;
use SGW_Import\Model\ImportFileListModel;

$page_security = 'SA_SGW_IMPORT_FILE';

$path_to_root = "../..";
$path_to_module = __DIR__;

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/ui/db_pager_view.inc");

class ImportFilesView
{

    /**
     * @var ImportFiles
     */
    public $controller;

    public function viewList()
    {
        start_form();

        start_table(TABLESTYLE, "width=70%");
        table_header(array(
            checkbox('', 'select_all', null, true),
            _("#"),
            _("Bank Account"),
            _("Filename"),
            ""
        ));

        $due = false;

        $this->controller->table();

        end_table();
        end_form();

        br();
    }

    /**
     * @param ImportFileListModel $model
     * @param int
     */
    public function tableRow($model, &$k)
    {
        alt_table_row_color($k);

        check_cells('', 's_' . $model->id);
        hyperlink_params_td('import_file.php', $model->id, 'id=' . $model->id);
        label_cell($model->bankAccountName);
        // label_cell(sql2date($model->dtStart), "align='center'");
        hyperlink_params_td('import_file.php', $model->fileName, 'id=' . $model->id);
        label_cell(navi_button('d_' . $model->id, _('Delete'), true, ICON_DELETE));

        end_row();
    }

    public function addFile()
    {
        start_form(true);
        start_table(TABLESTYLE2);
        bank_accounts_list_row(_("Bank Account:"), 'bank_id', null, false);
        file_row(_("Import File") . ":", 'filename', 'filename');
        end_table(1);

        submit_center('upload', 'Upload', true, false, 'process');
        end_form();
    }

}

add_access_extensions();

$view = new ImportFilesView();
$controller = new ImportFiles($view);
$view->controller = $controller;

$js = "";
if ($SysPrefs->use_popup_windows)
    $js .= get_js_open_window(900, 600);

if (user_use_date_picker())
    $js .= get_js_date_picker();

page(_($help_context = "Import Bank Files"), false, false, "", $js);

$controller->run();

end_page();

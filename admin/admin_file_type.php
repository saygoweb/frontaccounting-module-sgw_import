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

use SGW\Mapper;
use SGW_Import\Controller\AdminFileType;
use SGW_Import\Import\Column;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\View\View;

$page_security = 'SA_SGW_IMPORT_FILE';

$path_to_root = "../../..";
$path_to_module = __DIR__;

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/ui/db_pager_view.inc");

class AdminFileTypeView
{

    /**
     * @var AdminFileTYpe
     */
    public $controller;

    public function view(ImportFileTypeModel $fileTypeModel)
    {
        Mapper::writeArray($fileTypeModel, $_POST);
        start_form();

        start_table(TABLESTYLE_NOBORDER);

        bank_accounts_list_row(_("Bank Account:"), 'bank_id', null, true);

        end_table(1);

        start_outer_table(TABLESTYLE2, "width='70%'");

        table_section(1);
        label_row(_('Columns:'), $this->controller->columns($fileTypeModel));
        text_row(_('Hidden:'), 'hide', $this->controller->hide($fileTypeModel), 50, 128);

        // table_section(2);

        end_outer_table();

        hidden('id', $fileTypeModel->id);
        br();
        submit_add_or_update_center(false, 'Update', 'both');
        br();

        start_table(TABLESTYLE, "width=70%");

        $header = [];
        $header[] = _('Party Column');
        $header[] = _('Match');
        $header[] = _('Type');
        $header[] = _('Document Column');
        $header[] = _('Match');
        $header[] = _('Type');
        $header[] = ''; // Button

        table_header($header);
        // display_heading('Lines');
        $this->controller->linesTable($fileTypeModel->bankId);

        end_table();
        // end_outer_table();

        end_form();

        br();
    }

    public function lineRow(ImportLineModel $lineModel, &$k)
    {
        alt_table_row_color($k);

        label_cell($lineModel->partyField);
        label_cell($lineModel->partyMatch);
        label_cell($lineModel->partyType);

        label_cell('');
        label_cell('');
        label_cell($lineModel->docType);

        // label_cell('');
        hyperlink_params_td('../import_line.php', 'Edit Line', 'id=' . $lineModel->id);

        // if ($row->lineId !== null) {
        //     hyperlink_params_td('import_line.php', 'Edit Line', 'id=' . $row->lineId);
        // } else {
        //     label_cell(navi_button('a_' . $row->rowIndex, _('Add Line'), true, ICON_ADD));
        // }

        end_row();

    }

}

add_access_extensions();

$view = new AdminFileTypeView();
$controller = new AdminFileType($view);
$view->controller = $controller;

$js = "";
if ($SysPrefs->use_popup_windows)
    $js .= get_js_open_window(900, 600);

if (user_use_date_picker())
    $js .= get_js_date_picker();

page(_($help_context = "Import Bank Files"), false, false, "", $js);

$controller->run();

end_page();

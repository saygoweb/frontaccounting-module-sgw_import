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

// use SGW_Sales\db\GenerateRecurringModel;
use SGW_Import\Controller\ImportFile;
use SGW_Import\Import\Column;
use SGW_Import\Import\CsvFile;
use SGW_Import\Import\Row;
use SGW_Import\Model\ImportFileTypeModel;

$page_security = 'SA_SGW_IMPORT_FILE';

$path_to_root = "../..";
$path_to_module = __DIR__;

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/ui/db_pager_view.inc");

class ImportFileView
{

    /**
     * @var ImportFile
     */
    public $controller;

    public function viewList(CsvFile $file, array $columns)
    {
        start_form();

        start_table(TABLESTYLE_NOBORDER);
        start_row();

        check_cells('Show All', 'show_all', null, true);
        submit_cells('RunDataImport', _("Import Data"), '', _('Select lines'), 'default');

        end_row();
        end_table(1);

        hidden('id', $file->id);

        start_table(TABLESTYLE, "width=70%");

        $header = [];
        $header[] = checkbox('', 'select_all', null, true);
        $header[] = '#';
        foreach ($columns as $column) {
            if ($column->hidden) {
                continue;
            }
            $header[] = $column->name;
        }
        $header[] = 'Type';
        $header[] = 'Code';
        $header[] = ''; // Button

        table_header($header);

        $due = false;

        $this->controller->table($columns);

        end_table();
        end_form();

        br();
    }

    /**
     * @param Row $row
     * @param int
     */
    public function tableRow(Row $row, /** @var ImportLineModel */$matchingLine, array $columns, &$k)
    {
        alt_table_row_color($k);

        if ($matchingLine !== null) {
            check_cells('', 's_' . $row->rowIndex);
        } else {
            label_cell('');
        }
        label_cell($row->rowIndex);
        // Columns
        $c = count($columns);
        for ($i = 0; $i < $c; $i++) {
            if ($columns[$i]->hidden) {
                continue;
            }
            label_cell($row->data[$i]);
        }
        // Line details
        if (!$matchingLine) {
            label_cell('');
            label_cell('');
            label_cell(navi_button('a_' . $row->rowIndex, _('Add Line'), true, ICON_ADD));
        } else {
            label_cell($matchingLine->partyType);
            label_cell($matchingLine->partyCode);
            hyperlink_params_td('import_line.php', 'Edit Line', 'id=' . $row->lineId);
        }

        end_row();
    }

    public function generatedInvoice($orderNo)
    {
        echo 'Generated invoice for order ' . $orderNo;
        echo '<br/>';
    }
}

add_access_extensions();

$view = new ImportFileView();
$controller = new ImportFile($view);
$view->controller = $controller;

$js = "";
if ($SysPrefs->use_popup_windows)
    $js .= get_js_open_window(900, 600);

if (user_use_date_picker())
    $js .= get_js_date_picker();

page(_($help_context = "Import Bank Files"), false, false, "", $js);

$controller->run();

end_page();

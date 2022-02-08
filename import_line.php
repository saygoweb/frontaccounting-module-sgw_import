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
use SGW_Import\Controller\ImportLine;
use SGW_Import\Import\Column;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\View\View;

$page_security = 'SA_SGW_IMPORT_FILE';

$path_to_root = "../..";
$path_to_module = __DIR__;

include_once($path_to_root . "/includes/session.inc");
include_once($path_to_root . "/includes/ui.inc");
include_once($path_to_root . "/includes/ui/db_pager_view.inc");

class ImportLineView
{

    /**
     * @var ImportLine
     */
    public $controller;

    public function view(ImportLineModel $lineModel, ImportFileTypeModel $fileTypeModel)
    {
        Mapper::writeArray($lineModel, $_POST, ['partyField', 'docField']);
        $_POST['party_field'] = $fileTypeModel->columnKey($lineModel->partyField);
        $_POST['doc_field'] = $fileTypeModel->columnKey($lineModel->docField);
        
        start_form();

        start_outer_table(TABLESTYLE2, "width='70%'");

        table_section(1);

        display_heading("Bank Details");
        bank_accounts_list_row(_("Bank Account:"), 'bank_id', null, false);

        table_section(2);
        display_heading(_('Contra-Party'));

        $control = View::combo('party_field', $fileTypeModel->columns);
        label_row(_('Field Name:'), $control);
        text_row(_('Matching:'), 'party_match', $lineModel->partyMatch, 30, 128);
        // $_POST['party_type'] = 'supplier';
        label_row(_('Party Type:'),
            radio('Customer', 'party_type', ImportLineModel::PT_CUSTOMER, null, true) .
            radio('Supplier', 'party_type', ImportLineModel::PT_SUPPLIER, null, true) .
            radio('Quick Entry', 'party_type', ImportLineModel::PT_QUICK, null, true) .
            radio('Transfer', 'party_type', ImportLineModel::PT_TRANSFER, null, true)
        );

        label_row(_('Party Code:'), $lineModel->partyCode);

        if ($_POST['party_type'] == ImportLineModel::PT_CUSTOMER) {
            customer_list_row(_('Customer:'), 'party_id');
        } elseif ($_POST['party_type'] == ImportLineModel::PT_SUPPLIER) {
            supplier_list_row(_('Supplier:'), 'party_id');
        } elseif ($_POST['party_type'] == ImportLineModel::PT_QUICK) {
            quick_entries_list_row('Quick Entry:', 'party_id', null, QE_PAYMENT);
        } else {
            bank_accounts_list_row(_('Bank Account:'), 'party_id');
        }
        // customer_list_row(_("Item:"), 'customer_id', null, false, true, false, true);

        
        table_section(3);
        display_heading(_('Document'));
        $control = View::combo('doc_field', $fileTypeModel->columns);
        label_row(_('Field Name:'), $control);
        text_row(_('Matching:'), 'doc_match', get_post('doc_match'), 30, 128);
        label_row(_('Document Type:'),
            radio('None', 'doc_type', 'none', null, true) .
            radio('Supplier Invoice', 'doc_type', 'supplier_invoice', null, true)
        );
        label_row(_('Document Code:'), $lineModel->docCode);
        // if ($lineModel->docType == ImportLineModel::DT_SUPPLIER_INVOICE) {
        // Note: The control below is hard-coded to be named 'item_id'
        $_POST['stock_id'] = $lineModel->docCode;
        // label_row(_('Item:'), sales_items_list('item_id', null, false, false, '', array(
        //     'cells' => true,
        //     'where'=>array("NOT no_purchase")
        // )));
        stock_items_list_cells(null, 'stock_id', null, false, false, false, true, array('editable' => 30, 'where'=>array("NOT no_purchase")));
        // }

        end_outer_table();
        hidden('id', $lineModel->id);
        br();
        submit_add_or_update_center(false, 'Update', 'both');

        end_form();

        br();
    }

}

add_access_extensions();

$view = new ImportLineView();
$controller = new ImportLine($view);
$view->controller = $controller;

$js = "";
if ($SysPrefs->use_popup_windows)
    $js .= get_js_open_window(900, 600);

if (user_use_date_picker())
    $js .= get_js_date_picker();

page(_($help_context = "Import Bank Files"), false, false, "", $js);

$controller->run();

end_page();

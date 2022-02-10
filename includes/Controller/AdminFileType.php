<?php

namespace SGW_Import\Controller;

use SGW_Import\Import\CsvFile;
use SGW_Import\Import\Lines;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;

class AdminFileType
{

    /**
     * @var \AdminFileTypeView
     */
    private $view;

    public function __construct($view)
    {
        $this->view = $view;
    }

    public function run()
    {
        global $Ajax;
        $fileTypeModel = null;
        if (list_updated('bank_id')) {
            $Ajax->activate('page_body');
            $bankId = get_post('bank_id');
            $fileTypeModel = ImportFileTypeModel::findByBankId($bankId);
        } elseif (isset($_POST['id'])) {
            $id = get_post('id');
            $Ajax->activate('page_body');
            $fileTypeModel = new ImportFileTypeModel();
            $fileTypeModel->readOrThrow($id);
            $fileTypeModel->hide = explode(',', $_POST['hide']);
            $fileTypeModel->dateField = $fileTypeModel->columns[get_post('date_field')];
            $fileTypeModel->amountField = $fileTypeModel->columns[get_post('amount_field')];
            if (get_post('UPDATE_ITEM')) {
                $fileTypeModel->write();
            } elseif (get_post('RESET')) {
                $fileTypeModel->readOrThrow($id);
            }
        }
        if (!$fileTypeModel) {
            $fileTypeModel = ImportFileTypeModel::findOne();
        }
        $this->view->view($fileTypeModel);
    }

    public function columns(ImportFileTypeModel $fileTypeModel)
    {
        return implode(',', $fileTypeModel->columns);
    }

    public function hide(ImportFileTypeModel $fileTypeModel)
    {
        return implode($fileTypeModel->hide);
    }

    public function linesTable($bankId)
    {
        $k = 0;
        $lineModels = ImportLineModel::findByBankId($bankId);
        foreach ($lineModels as $lineModel) {
            $this->view->lineRow($lineModel, $k);
        }
    }

}

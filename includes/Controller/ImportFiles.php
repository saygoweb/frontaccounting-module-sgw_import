<?php

namespace SGW_Import\Controller;

use SGW_Import\Import\CsvFile;
use SGW_Import\Model\ImportFileListModel;
use SGW_Import\Model\ImportFileModel;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Upload\Assets;

class ImportFiles
{

    /**
     * @var \ImportFilesView
     */
    private $view;

    /**
     * @var Assets
     */
    private $assets;


    const FORCE_NO    = 'no';
    const FORCE_CHECK = '1';
    const FORCE_CLEAR = '0';

    private $force;

    public function __construct($view)
    {
        $this->view = $view;
        $this->assets = new Assets();
    }

    public function run()
    {
        global $Ajax;
        if (get_post('upload')) {
            $Ajax->activate('_page_body');
            if ($_FILES['filename']['error'] == 4) {
                display_error('Choose a file before uploading');
            } else {
                $bankId = get_post('bank_id');;
                $importFileModel = null;
                if ($this->assets->exists('filename')) {
                    $importFileModel = ImportFileModel::findByFileName($this->assets->filename('filename'));
                }
                if (!$importFileModel) {
                    $importFileModel = new ImportFileModel();
                }
                $importFileModel->bankId = $bankId;
                $importFileModel->fileName = $this->assets->storeUploadedFile('filename');
                $importFileModel->write();
                // TODO Add / Update ImportFileType for bank account and column definition
                $importFileTypeModel = ImportFileTypeModel::findByBankId($bankId);
                if (!$importFileTypeModel) {
                    $importFileTypeModel = new ImportFileTypeModel();
                    $csvFile = new CsvFile($importFileModel->id);
                    $importFileTypeModel->bankId = $bankId;
                    $importFileTypeModel->columns = $csvFile->columns;
                    $importFileTypeModel->write();
                }
            }
        }
        $idDelete = null;
        foreach ($_POST as $key => $value) {
            if ($key[0] != 'd') {
                continue;
            }
            $tokens = explode('_', $key) ?? [];
            if (count($tokens) != 2) {
                continue;
            }
            if ($tokens[0] == 'd') {
                $idDelete = $tokens[1];
                break;
            }
        }
        // if (isset($_GET['delete']) && $_GET['delete']) {
        if ($idDelete) {
            $Ajax->activate('_page_body');
            $importFileModel = new ImportFileModel();
            $importFileModel->readOrThrow($idDelete);
            $this->assets->delete($importFileModel->fileName);
            ImportFileModel::delete($idDelete);
        }
        if (get_post('delete_all')) {
            $Ajax->activate('_page_body');
            foreach ($_POST as $key => $value) {
                if (strpos($key, 's_') === false) {
                    continue;
                }
                if ($value) {
                //     $parts = explode('_', $key);
                //     $orderNo = $parts[1];
                //     $model = SalesRecurringModel::readByTransNo($orderNo);
                //     $invoiceNo = $this->generateInvoice($orderNo, self::comment($model, new \DateTime()));
                //     $this->emailInvoice($invoiceNo);
                //     $next = self::nextDateAfter($model, new \DateTime());
                //     $model->dtNext = $next->format('Y-m-d');
                //     $model->write();
                }
            }
            return;
        }
        if (list_updated('select_all')) {
            $Ajax->activate('_page_body');
            $this->force = check_value('select_all') ? self::FORCE_CHECK : self::FORCE_CLEAR;
        }
        $this->view->viewList();
        $this->view->addFile();
    }

    public function table()
    {
        $k = 0;
        $result = ImportFileListModel::find();
        foreach ($result as $model) {
            if ($this->force != self::FORCE_NO) {
                $key = 's_' . $model->id;
                $_POST[$key] = $this->force;
            }
            $this->view->tableRow($model, $k);
        }
    }

}

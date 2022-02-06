<?php

namespace SGW_Import\Controller;

use SGW\Mapper;
use SGW_Import\Import\CsvFile;
use SGW_Import\Import\Lines;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;

class ImportLine
{

    /**
     * @var \ImportLineView
     */
    private $view;

    /** @var CsvFile */
    private $file;

    const FORCE_NO    = 'no';
    const FORCE_CHECK = '1';
    const FORCE_CLEAR = '0';

    private $force;

    private $id;

    public function __construct($view)
    {
        $this->view = $view;
    }

    public function run()
    {
        global $Ajax;
        if (!isset($_GET['id']) && !isset($_POST['id'])) {
            throw new \Exception('id not set');
        }
        $this->id = $_GET['id'] ?? $_POST['id'];

        $lineModel = new ImportLineModel();
        $lineModel->readOrThrow($this->id);

        $fileTypeModel = ImportFileTypeModel::findByBankId($lineModel->bankId);
        if (!$fileTypeModel) {
            throw new \Exception('Import file type for bank id \'' . $lineModel->bankId . '\' not found');
        }

        if (isset($_POST['id'])) {
            $_POST['doc_item_id'] = $_POST['stock_id'];
            Mapper::writeModel($_POST, $lineModel, ['partyCode']);
        }

        if (list_updated('party_type') || list_updated('doc_type')) {
            $Ajax->activate('_page_body');
            $lineModel->partyType = get_post('party_type');
            $lineModel->docType = get_post('doc_type');
        } elseif (get_post('UPDATE_ITEM')) {
            $Ajax->activate('_page_body');
            $lineModel->write();
        } elseif (get_post('RESET')) {
            $Ajax->activate('_page_body');
            $lineModel = new ImportLineModel();
            $lineModel->readOrThrow($this->id);
        }

        $lineModel->fixDefaults();
        $this->view->view($lineModel, $fileTypeModel);
    }

}

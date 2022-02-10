<?php

namespace SGW_Import\Controller;

use SGW\Mapper;
use SGW_Import\Import\CsvFile;
use SGW_Import\Import\Lines;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;
use SGW_Import\Model\PartyCodeModel;

class ImportLine
{

    /**
     * @var \ImportLineView
     */
    private $view;

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
            $_POST['doc_code'] = $_POST['stock_id'];
            Mapper::writeModel($_POST, $lineModel, ['partyCode', 'partyField', 'docField']);
            $lineModel->partyField = $fileTypeModel->columns[get_post('party_field')];
            $lineModel->docField = $fileTypeModel->columns[get_post('doc_field')];
        }

        if (list_updated('party_type') || list_updated('doc_type')) {
            $Ajax->activate('_page_body');
            $lineModel->partyType = get_post('party_type');
            $lineModel->docType = get_post('doc_type');
        }
        if (isset($_POST['id'])) {
            if ($lineModel->docType == ImportLineModel::DT_NONE || $lineModel->docType == ImportLineModel::DT_CUSTOMER_INVOICE) {
                $lineModel->docCode = '';
            }
        }
        if (get_post('UPDATE_ITEM')) {
            $Ajax->activate('_page_body');
            $lineModel->partyCode = $this->partyCode($lineModel->partyType, $lineModel->partyId);
            $lineModel->write();
        } elseif (get_post('RESET')) {
            $Ajax->activate('_page_body');
            $lineModel = new ImportLineModel();
            $lineModel->readOrThrow($this->id);
        }

        $lineModel->fixDefaults();
        $this->view->view($lineModel, $fileTypeModel);
    }

    public function partyCode(string $partyType, $partyId)
    {
        $partyCode = null;
        switch ($partyType) {
            case ImportLineModel::PT_CUSTOMER:
                $partyCode = PartyCodeModel::findByCustomer($partyId);
                break;
            case ImportLineModel::PT_SUPPLIER:
                $partyCode = PartyCodeModel::findBySupplier($partyId);
                break;
            case ImportLineModel::PT_QUICK:
                $partyCode = PartyCodeModel::findByQuick($partyId);
                break;
            case ImportLineModel::PT_TRANSFER:
                $partyCode = PartyCodeModel::findByBank($partyId);
                break;
            default:
                throw new \Exception('Unsupported Party Type: ' . $partyType);
        }
        if (!$partyCode) {
            throw new \Exception(sprintf('Could not find Party Code for Party Type %s id %s', $partyType, $partyId));
        }
        return $partyCode->code;
    }

}

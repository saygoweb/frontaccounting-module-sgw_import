<?php

namespace SGW_Import\Controller;

use SGW_Import\Import\Column;
use SGW_Import\Import\CsvFile;
use SGW_Import\Import\Importers\Importer;
use SGW_Import\Import\Importers\ImportState;
use SGW_Import\Import\Lines;
use SGW_Import\Import\Row;
use SGW_Import\Import\RowStatus;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;

class ImportFile
{

    /**
     * @var \ImportFileView
     */
    private $view;

    /** @var CsvFile */
    private $file;

    /** @var ImportFileTypeModel */
    private $fileType;

    const FORCE_NO    = 'no';
    const FORCE_CHECK = '1';
    const FORCE_CLEAR = '0';

    private $force;

    public function __construct($view)
    {
        $this->view = $view;
        $this->force = self::FORCE_NO;
    }

    public function run()
    {
        global $Ajax;
        if (!isset($_GET['id']) && !isset($_POST['id'])) {
            throw new \Exception('id not set');
        }
        $this->id = $_GET['id'] ?? $_POST['id'];
        if (list_updated('select_all')) {
            $Ajax->activate('_page_body');
            $this->force = check_value('select_all') ? self::FORCE_CHECK : self::FORCE_CLEAR;
        }
        $idAddLine = null;
        foreach ($_POST as $key => $value) {
            if ($key[0] != 'a') {
                continue;
            }
            $tokens = explode('_', $key);
            if (count($tokens) != 2) {
                continue;
            }
            if ($tokens[0] == 'a') {
                $idAddLine = $tokens[1];
                break;
            }
        }
        // if (isset($_GET['delete']) && $_GET['delete']) {
        try {
            $this->file = new CsvFile($this->id);
            $this->fileType = ImportFileTypeModel::findByBankId($this->file->importFileModel->bankId);
            if ($idAddLine) {
                $Ajax->activate('_page_body');
                $row = $this->file->readRow($idAddLine);
                $lineModel = new ImportLineModel();
                $lineModel->bankId = $this->file->importFileModel->bankId;
                $lineModel->partyMatch = $row->data[2]; // TODO
                // $lineModel->partyType = ImportLineModel::PARTY_SUPPLIER;
                $lineModel->partyField = 'Payee';
                $lineModel->write();
                $this->file->reset();
            }
    
            $this->lines = new Lines($this->file->importFileModel->bankId);
            $this->columns = Column::createByArray($this->fileType->columns, $this->fileType->hide);
            $this->view->viewList($this->file, $this->columns);
            $this->file->close();
        } catch (\Exception $e) {
            $this->view->displayError($e->getMessage());
        }
    }

    /**
     * @return bool
     */
    public function columnHidden(string $column)
    {
        return in_array($column, $this->fileType->hide);
    }

    public function table($columns)
    {
        global $Ajax;
        $k = 0;
        $doImport = get_post('submit_import');
        $countImport = 0;
        if ($doImport) {
            $Ajax->activate('_page_body');
        }
        $importState = new ImportState();
        while ($row = $this->file->read()) {
            $key = 's_' . $row->rowIndex;
            if ($this->force != self::FORCE_NO) {
                $_POST[$key] = $this->force;
            }
            $matchingLine = $this->lines->matchingLine($row);
            if ($doImport) {
                $row->status = new RowStatus();
            }
            if ($doImport && isset($_POST[$key]) && $_POST[$key]) {
                $countImport++;
                $this->importLine($row, $matchingLine, $importState);
            }
            $this->view->tableRow($row, $matchingLine, $columns, $doImport, $k);
        }
        if ($doImport && $countImport == 0) {
            display_error('No lines selected for import.');
        }
    }

    public function importLine(Row $row, ImportLineModel $line, ImportState $importState)
    {
        $importer = Importer::fromPartyType($line->partyType, $this->fileType, $importState);
        if (!$importer->transactionExists($row, $line)) {
            if (get_post('dry_run')) {
                $row->status->status = RowStatus::STATUS_TODO;
            } else {
                $importer->addTransaction($row, $line);
            }
        }
    }

}

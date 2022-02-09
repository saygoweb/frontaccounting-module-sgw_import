<?php
namespace SGW_Import\Import\Importers;

use SGW_Import\Import\Row;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;

abstract class Importer
{
    public static function fromPartyType($partyType)
    {
        switch ($partyType) {
            case ImportLineModel::PT_CUSTOMER:
                return new CustomerImporter();
            case ImportLineModel::PT_SUPPLIER:
                return new SupplierImporter();
            case ImportLineModel::PT_QUICK:
                return new QuickImporter();
            case ImportLineModel::PT_TRANSFER:
                return new TransferImporter();
        }
        throw new \Exception("Could not create Importer for '$partyType'");
    }

    public function import(Row $row, ImportLineModel $line, ImportFileTypeModel $fileType)
    {
        if (!$this->transactionExists($row, $line, $fileType)) {
            $this->addTransaction($row, $line, $fileType);
        }
    }

    protected $dateColumn = 0;
    protected $amountColumn = 1;

    abstract public function transactionExists(Row $row, ImportLineModel $line, ImportFileTypeModel $fileType);

    abstract public function addTransaction(Row $row, ImportLineModel $line, ImportFileTypeModel $fileType);

}

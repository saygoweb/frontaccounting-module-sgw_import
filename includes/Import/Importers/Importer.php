<?php
namespace SGW_Import\Import\Importers;

use SGW_Import\Import\Row;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;

abstract class Importer
{
    public static function fromPartyType($partyType, ImportFileTypeModel $fileType)
    {
        switch ($partyType) {
            case ImportLineModel::PT_CUSTOMER:
                return new CustomerImporter($fileType);
            case ImportLineModel::PT_SUPPLIER:
                return new SupplierImporter($fileType);
            case ImportLineModel::PT_QUICK:
                return new QuickImporter($fileType);
            case ImportLineModel::PT_TRANSFER:
                return new TransferImporter($fileType);
        }
        throw new \Exception("Could not create Importer for '$partyType'");
    }

    protected $dateColumn = 0;
    protected $amountColumn = 1;

    /** @var ImportFileTypeModel */
    protected $fileType;

    protected function __construct(ImportFileTypeModel $fileType)
    {
        $this->fileType = $fileType;
        $this->dateColumn = $fileType->columnKey($fileType->dateField);
        $this->amountColumn = $fileType->columnKey($fileType->amountField);
    }


    abstract public function transactionExists(Row $row, ImportLineModel $line);

    abstract public function addTransaction(Row $row, ImportLineModel $line);

}

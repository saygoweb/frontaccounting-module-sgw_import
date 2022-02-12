<?php
namespace SGW_Import\Import\Importers;

use SGW_Import\Import\Row;
use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;

abstract class Importer
{
    public static function fromPartyType($partyType, ImportFileTypeModel $fileType, ImportState $importState)
    {
        switch ($partyType) {
            case ImportLineModel::PT_CUSTOMER:
                return new CustomerImporter($fileType, $importState);
            case ImportLineModel::PT_SUPPLIER:
                return new SupplierImporter($fileType, $importState);
            case ImportLineModel::PT_QUICK:
                return new QuickImporter($fileType, $importState);
            case ImportLineModel::PT_TRANSFER:
                return new TransferImporter($fileType, $importState);
        }
        throw new \Exception("Could not create Importer for '$partyType'");
    }

    protected $dateColumn = 0;
    protected $amountColumn = 1;

    /** @var ImportFileTypeModel */
    protected $fileType;

    /** @var ImportState */
    protected $importState;

    protected function __construct(ImportFileTypeModel $fileType, ImportState $importState)
    {
        $this->fileType = $fileType;
        $this->importState = $importState;
        $this->dateColumn = $fileType->columnKey($fileType->dateField);
        $this->amountColumn = $fileType->columnKey($fileType->amountField);
    }

    public function sqlDate($data)
    {
        switch ($this->fileType->dateFormat) {
            case ImportFileTypeModel::DTF_YYYYMMDD:
                return $data;
            case ImportFileTypeModel::DTF_DDMMYY:
                if (strlen($data) != 8) {
                    throw new \Exception(sprintf("Bad date '%s'", $data));
                }
                $d = \DateTime::createFromFormat('d/m/y', $data);
                return $d->format('Y-m-d');
            default:
                throw new \Exception(sprintf("Unsupported date format '%s'", $this->fileType->dateFormat));
        }
    }

    public function docReference(Row $row, ImportLineModel $line)
    {
        $column = $this->fileType->columnKey($line->docField);
        $haystack = $row->data[$column];
        $expression = $line->docMatch;
        if (!$expression) {
            return null;
        }
        $matches = [];
        $result = preg_match($expression, $haystack, $matches);
        if ($result == 1) {
            return $matches[0];
        }
        return null;
    }

    abstract public function transactionExists(Row $row, ImportLineModel $line);

    abstract public function addTransaction(Row $row, ImportLineModel $line);

}

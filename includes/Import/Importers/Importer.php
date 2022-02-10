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
                throw new \Exception(sprintf("Unsupported data format '%s'", $this->fileType->dateFormat));
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

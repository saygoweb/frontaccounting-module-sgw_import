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
            case ImportLineModel::PT_QUICK_DEPOSIT:
                return new QuickDepositImporter($fileType, $importState);
            case ImportLineModel::PT_QUICK_PAYMENT:
                return new QuickPaymentImporter($fileType, $importState);
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
                return self::parseDate('Y-m-d', $data);
            case ImportFileTypeModel::DTF_DDMMYY:
                return self::parseDate('d/m/y', $data);
            default:
                throw new \Exception(sprintf("Unsupported date format '%s'", $this->fileType->dateFormat));
        }
    }

    /**
     * MySQL stores a malformed date as 0000-00-00 or reads it as another date,
     * so refuse anything that does not format back to exactly what was read.
     */
    private static function parseDate(string $format, string $data): string
    {
        $d = \DateTime::createFromFormat('!' . $format, $data);
        if ($d === false || $d->format($format) !== $data) {
            throw new \Exception(sprintf("Bad date '%s'", $data));
        }
        return $d->format('Y-m-d');
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

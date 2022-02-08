<?php
namespace SGW_Import\Import;

use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;

class Lines
{
    /** @var ImportLineModel[] */
    private $idxPartyMatch = [];

    /** @var ImportLineModel[] */
    private $lines = [];

    /** @var ImportFileTypeModel */
    private $fileType;

    /** @var bool[] */
    private $partyColumns = [];

    public function __construct(int $bankId)
    {
        $this->fileType = ImportFileTypeModel::findByBankId($bankId);
        $models = ImportLineModel::findByBankId($bankId);
        foreach ($models as $model) {
            $m = clone $model;
            $this->lines[] = $m;
            $this->idxPartyMatch[strtolower($model->partyMatch)] = $m;
            $columnKey = $this->fileType->columnKey($model->partyField);
            $this->partyColumns[$columnKey] = true;
        }
    }

    /**
     * @return ImportLineModel|null
     */
    public function matchingLine(Row $row)
    {
        foreach ($this->partyColumns as $columnKey => $value) {
            $key = strtolower($row->data[$columnKey]);
            if (array_key_exists($key, $this->idxPartyMatch)) {
                return $this->idxPartyMatch[$key];
            }
        }
        return null;
    }

    public function partyLineId(Row $row)
    {
        $column = 2;
        $key = strtolower($row->data[$column]);
        if (array_key_exists($key, $this->idxPartyMatch)) {
            return $this->idxPartyMatch[$key]->id;
        }
        return null;
    }

}
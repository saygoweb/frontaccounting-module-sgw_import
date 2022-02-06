<?php
namespace SGW_Import\Import;

use SGW_Import\Model\ImportLineModel;

class Lines
{
    public $idxPartyMatch = [];

    public function __construct(int $bankId)
    {
        $models = ImportLineModel::findByBankId($bankId);
        foreach ($models as $model) {
            $this->idxPartyMatch[strtolower($model->partyMatch)] = $model->id;
        }
    }

    public function partyLineId(Row $row)
    {
        $column = 2;
        $key = strtolower($row->data[$column]);
        if (array_key_exists($key, $this->idxPartyMatch)) {
            return $this->idxPartyMatch[$key];
        }
        return null;
    }

}
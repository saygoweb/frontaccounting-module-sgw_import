<?php
namespace SGW_Import\Import;

use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;

class Lines
{
    /** @var ImportLineModel[][] lines by column key, then by lower case party match */
    private $idxPartyMatch = [];

    /** @var ImportLineModel[] */
    private $lines = [];

    /** @var ImportFileTypeModel */
    private $fileType;

    public function __construct(int $bankId)
    {
        $this->fileType = ImportFileTypeModel::findByBankId($bankId);
        $models = ImportLineModel::findByBankId($bankId);
        foreach ($models as $model) {
            $m = clone $model;
            $this->lines[] = $m;
            $columnKey = $this->fileType->columnKey($model->partyField);
            $this->idxPartyMatch[$columnKey][strtolower($model->partyMatch)] = $m;
        }
    }

    /**
     * The line whose party match is the whole of its column in $row, ignoring
     * case; failing that, the line with the longest party match found within
     * its column, so 'Google CLOUD' matches 'Google CLOUD VL8RQC'.
     *
     * @return ImportLineModel|null
     */
    public function matchingLine(Row $row)
    {
        foreach ($this->idxPartyMatch as $columnKey => $lines) {
            $key = strtolower($row->data[$columnKey] ?? '');
            if (array_key_exists($key, $lines)) {
                return $lines[$key];
            }
        }
        $best = null;
        $bestLength = 0;
        foreach ($this->idxPartyMatch as $columnKey => $lines) {
            $haystack = strtolower($row->data[$columnKey] ?? '');
            foreach ($lines as $match => $line) {
                $match = (string) $match;
                $length = strlen($match);
                if ($length > $bestLength && strpos($haystack, $match) !== false) {
                    $best = $line;
                    $bestLength = $length;
                }
            }
        }
        return $best;
    }

    public function partyLineId(Row $row)
    {
        $line = $this->matchingLine($row);
        return $line ? $line->id : null;
    }

}

<?php
namespace SGW_Import\Import\Importers;

class ImportState
{
    private $dailyPartyCount;

    private $currentDate;

    public function __construct()
    {
        $this->dailyPartyCount = [];
        $this->currentDate = '';
    }

    public function pushPartyAmount($sqlDate, $partyCode, $amount, $docNumber)
    {
        if ($this->currentDate != $sqlDate) {
            $this->currentDate = $sqlDate;
            $this->dailyPartyCount = [];
        }
        $key = $this->key($partyCode, $amount);
        if (!array_key_exists($key, $this->dailyPartyCount)) {
            $this->dailyPartyCount[$key] = [];
        }
        if (isset($this->dailyPartyCount[$key][$docNumber])) {
            throw new \Exception("Pushing document that is already pushed: '$docNumber' $sqlDate, $partyCode, $amount");
        }
        $this->dailyPartyCount[$key][$docNumber] = 1;
    }

    public function isUsed($sqlDate, $partyCode, $amount, $docNumber)
    {
        $key = $this->key($partyCode, $amount);
        if (!array_key_exists($key, $this->dailyPartyCount)) {
            return false;
        }
        if (!isset($this->dailyPartyCount[$key][$docNumber])) {
            return false;
        }
        return true;
    }

    // public function countPartyAmount($sqlDate, $partyCode, $amount)
    // {
    //     $key = $this->key($partyCode, $amount);
    //     if (!array_key_exists($key, $this->dailyPartyCount)) {
    //         return 0;
    //     }
    //     return $this->dailyPartyCount[$key];
    // }

    private function key($partyCode, $amount)
    {
        return $partyCode . '_' . $amount;
    }
}
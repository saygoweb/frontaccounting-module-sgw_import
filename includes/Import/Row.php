<?php
namespace SGW_Import\Import;

class Row
{
    public $data = [];

    /** @var int */
    public $rowIndex = 0;

    /** @var RowStatus */
    public $status;

    public function __construct()
    {
        $this->status = new RowStatus();
    }
}

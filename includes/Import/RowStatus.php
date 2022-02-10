<?php
namespace SGW_Import\Import;

class RowStatus
{
    const STATUS_EXISTING = 'Exists';
    const STATUS_NEW      = 'New';
    const STATUS_TODO     = 'To Do';
    const STATUS_MANUAL   = 'Manual';

    /** @var string */
    public $status;

    public $documentType;

    public $documentId;

    /** @var string */
    public $link;

}

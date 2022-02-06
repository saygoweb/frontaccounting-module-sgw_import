<?php
namespace SGW_Import\Model;

use Anorm\Anorm;
use Anorm\DataMapper;
use Anorm\Model;
use SGW\DB;

class ImportFileListModel extends ImportFileModel
{
    /**
     * @return Generator<ImportFileListModel>|ImportFileListModel[]|boolean
     */
    public static function find()
    {
        $result = DataMapper::find(ImportFileListModel::class, Anorm::pdo())
            ->select('f.*,ba.bank_account_name')
            ->from('`0_import_file` AS f')
            ->join('INNER JOIN `0_bank_accounts` AS ba ON f.bank_id=ba.id')
            ->some();
        return $result;
    }

    /** @var string */
    public $bankAccountName;

}

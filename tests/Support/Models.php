<?php

namespace SGW_Import\Tests\Support;

use SGW_Import\Model\ImportFileTypeModel;
use SGW_Import\Model\ImportLineModel;

/**
 * sgw_import's models for unit tests, made without their constructors: those
 * need an Anorm connection, which a unit test has none of.
 */
final class Models
{
    /**
     * A file type with Date, Amount and Payee columns, dates as yyyymmdd.
     *
     * @param array<string, mixed> $properties
     */
    public static function fileType(array $properties = []): ImportFileTypeModel
    {
        return self::make(ImportFileTypeModel::class, array_merge([
            'bankId' => 1,
            'columns' => ['Date', 'Amount', 'Payee'],
            'hide' => [],
            'dateField' => 'Date',
            'amountField' => 'Amount',
            'dateFormat' => ImportFileTypeModel::DTF_YYYYMMDD,
        ], $properties));
    }

    /**
     * @param array<string, mixed> $properties
     */
    public static function line(array $properties = []): ImportLineModel
    {
        return self::make(ImportLineModel::class, array_merge([
            'bankId' => 1,
            'partyField' => 'Payee',
            'partyType' => ImportLineModel::PT_SUPPLIER,
            'docType' => ImportLineModel::DT_NONE,
        ], $properties));
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $properties
     * @return T
     */
    private static function make(string $class, array $properties): object
    {
        $model = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        foreach ($properties as $name => $value) {
            $model->$name = $value;
        }

        return $model;
    }
}

<?php

namespace SGW_Import\Tests\Support;

/**
 * Customers the demo company lacks, made from its own (FaRows removes them).
 */
final class DemoCustomers
{
    /** Demo customer 1, Donald Easter LLC: USD, cash only. */
    public const CASH = 1;

    /**
     * A copy of demo customer 1 and its branch on credit terms (payment within
     * 10 days): FrontAccounting pays a cash customer's invoice itself as it
     * writes it, which leaves nothing for an imported payment to allocate to.
     */
    public static function onCredit(\PDO $pdo): int
    {
        $pdo->exec(self::copyRow($pdo, 'debtors_master', 'debtor_no', [
            'debtor_ref' => "'SGW Import credit " . bin2hex(random_bytes(3)) . "'",
            'payment_terms' => '3',
        ], 'debtor_no = ' . self::CASH));
        $customer = (int) $pdo->lastInsertId();
        $pdo->exec(self::copyRow($pdo, 'cust_branch', 'branch_code', [
            'debtor_no' => (string) $customer,
        ], 'debtor_no = ' . self::CASH));

        return $customer;
    }

    /**
     * INSERT ... SELECT of a row of $table but its auto-increment $key, with
     * $values (SQL expressions) in place of those columns: whatever columns
     * this FrontAccounting's table has.
     *
     * @param array<string, string> $values
     */
    private static function copyRow(\PDO $pdo, string $table, string $key, array $values, string $where): string
    {
        $columns = $pdo->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = '0_$table' AND COLUMN_NAME <> '$key' ORDER BY ORDINAL_POSITION"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $select = array_map(function (string $c) use ($values): string {
            return $values[$c] ?? "`$c`";
        }, $columns);

        return "INSERT INTO 0_$table (`" . implode('`, `', $columns) . '`) SELECT ' . implode(', ', $select)
            . " FROM 0_$table WHERE $where";
    }
}

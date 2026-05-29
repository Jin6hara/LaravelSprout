<?php

use App\Enums\EventStatus;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseReportStatus;
use App\Enums\ExpenseTripType;
use App\Enums\Gender;
use App\Enums\LeaveCreditTransactionType;
use App\Enums\LeaveExcused;
use App\Enums\LeaveKind;
use App\Enums\LeaveStatus;
use App\Enums\RestPatternAdjustmentKind;
use App\Enums\RestPatternRuleKind;
use App\Enums\ShiftType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $columns = [
        'users' => [
            'gender' => Gender::class,
        ],
        'rest_pattern_rules' => [
            'kind' => RestPatternRuleKind::class,
        ],
        'rest_pattern_adjustments' => [
            'kind' => RestPatternAdjustmentKind::class,
        ],
        'leaves' => [
            'kind' => LeaveKind::class,
            'excused' => LeaveExcused::class,
            'status' => LeaveStatus::class,
        ],
        'events' => [
            'status' => EventStatus::class,
            'type' => ShiftType::class,
        ],
        'leave_credit_transactions' => [
            'type' => LeaveCreditTransactionType::class,
        ],
        'expense_reports' => [
            'status' => ExpenseReportStatus::class,
        ],
        'expenses' => [
            'trip_type' => ExpenseTripType::class,
            'category' => ExpenseCategory::class,
        ],
        'route_details' => [
            'trip_type' => ExpenseTripType::class,
        ],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($columns) as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach ($this->checkConstraintsForColumn($table, $column) as $constraint) {
                    DB::statement(sprintf(
                        'alter table %s drop constraint %s',
                        $this->quoteIdentifier($table),
                        $this->quoteIdentifier($constraint->conname)
                    ));
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->columns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column => $enumClass) {
                if (!Schema::hasColumn($table, $column)) {
                    continue;
                }

                $constraint = "{$table}_{$column}_allowed_check";
                if ($this->constraintExists($table, $constraint)) {
                    continue;
                }

                $values = implode(', ', array_map(
                    fn (string $value) => DB::getPdo()->quote($value),
                    $enumClass::values()
                ));

                DB::statement(sprintf(
                    'alter table %s add constraint %s check (%s in (%s))',
                    $this->quoteIdentifier($table),
                    $this->quoteIdentifier($constraint),
                    $this->quoteIdentifier($column),
                    $values
                ));
            }
        }
    }

    private function checkConstraintsForColumn(string $table, string $column): array
    {
        return DB::select(
            <<<'SQL'
select distinct con.conname
from pg_constraint con
join pg_class rel on rel.oid = con.conrelid
join pg_namespace nsp on nsp.oid = rel.relnamespace
join unnest(con.conkey) as cols(attnum) on true
join pg_attribute att on att.attrelid = rel.oid and att.attnum = cols.attnum
where con.contype = 'c'
  and nsp.nspname = current_schema()
  and rel.relname = ?
  and att.attname = ?
SQL,
            [$table, $column]
        );
    }

    private function constraintExists(string $table, string $constraint): bool
    {
        return (bool) DB::selectOne(
            <<<'SQL'
select 1
from pg_constraint con
join pg_class rel on rel.oid = con.conrelid
join pg_namespace nsp on nsp.oid = rel.relnamespace
where nsp.nspname = current_schema()
  and rel.relname = ?
  and con.conname = ?
limit 1
SQL,
            [$table, $constraint]
        );
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Enums\ExpenseReportStatus;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseTripType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->string('employee_code')->index();
            $t->string('employee_family_name');
            $t->string('employee_first_name')->nullable();
            $t->string('employee_middle_name')->nullable();

            $t->unsignedSmallInteger('year');
            $t->unsignedTinyInteger('month');

            $t->string('status', 32)
                ->default(ExpenseReportStatus::DRAFT->value)
                ->index();

            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('paid_at')->nullable();

            // 金額は最小単位（円）で保持
            $t->unsignedInteger('total_amount')->default(0);

            $t->timestamps();

            // 同一ユーザーの同一年月は一意
            $t->unique(['user_id', 'year', 'month']);
        });

        Schema::create('commuter_passes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->date('date_from')->index();
            $t->date('date_to')->index();

            $t->string('station_from');
            $t->string('station_to');

            $t->text('note')->nullable();
            $t->unsignedInteger('cost')->default(0); // 定期代（任意で使用）

            $t->timestamps();

            // 期間の大小関係はアプリ側でバリデーション
        });

        Schema::create('expenses', function (Blueprint $t) {
            $t->id();

            $t->foreignId('expense_report_id')
                ->constrained('expense_reports')
                ->cascadeOnDelete();

            $t->date('expense_date')->index();
            $t->unsignedInteger('seq')->default(100); // 同日複数行の並び順用

            $t->string('station_from')->nullable();
            $t->string('station_to')->nullable();

            $t->text('note')->nullable();

            $t->unsignedInteger('cost')->default(0);

            $t->string('trip_type', 32)
                ->default(ExpenseTripType::ROUND_TRIP->value);

            $t->string('category', 32)
                ->default(ExpenseCategory::REGULAR->value);

            $t->foreignId('commuter_pass_id')
                ->nullable()
                ->constrained('commuter_passes')
                ->nullOnDelete();

            $t->timestamps();
            // 同一レポート内で、日付＋並び順の組み合わせは一意
            $t->index(['expense_report_id', 'expense_date', 'seq']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table "expense_reports" add constraint "expense_reports_year_non_negative" check ("year" >= 0)');
            DB::statement('alter table "expense_reports" add constraint "expense_reports_month_non_negative" check ("month" >= 0)');
            DB::statement('alter table "expense_reports" add constraint "expense_reports_total_amount_non_negative" check ("total_amount" >= 0)');
            DB::statement('alter table "commuter_passes" add constraint "commuter_passes_cost_non_negative" check ("cost" >= 0)');
            DB::statement('alter table "expenses" add constraint "expenses_seq_non_negative" check ("seq" >= 0)');
            DB::statement('alter table "expenses" add constraint "expenses_cost_non_negative" check ("cost" >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('commuter_passes');
        Schema::dropIfExists('expense_reports');
    }
};

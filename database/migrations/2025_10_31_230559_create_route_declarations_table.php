<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Enums\ExpenseTripType;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('route_declarations', function (Blueprint $t) {
            $t->id();

            // 紐付け（運用に合わせて user_id or employee_code）
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Google Form等の提出時刻想定
            $t->timestamp('submitted_at')->nullable()->index(); // = Timestamp

            $t->string('closest_station');                       // Closest Station
            $t->string('train_line')->nullable();                // Train Line
            $t->date('effective_date');                          // Effective Date of New Expenses
            $t->text('reason')->nullable();                      // Reason for Submitting Expenses

            $t->timestamps();

            // 例：同一ユーザーが同じ発効日で複数作れないようにする場合
            // $t->unique(['user_id', 'effective_date']);
        });

        Schema::create('route_details', function (Blueprint $t) {
            $t->id();

            $t->foreignId('route_declaration_id')
                ->constrained('route_declarations')
                ->cascadeOnDelete();

            // DOW（曜日）: enum か tinyInteger で管理
            $t->string('dow', 3)->index(); // 'Mon','Tue','Wed','Thu','Fri','Sat','Sun'

            $t->string('from_station');    // from
            $t->string('to_station');      // to

            // round/oneway
            $t->string('trip_type', 32)->default(ExpenseTripType::ROUND_TRIP->value);

            // 金額（JPY想定）: 税込片道/往復の合計を記録
            $t->unsignedInteger('amount')->default(0);

            $t->string('route_text')->nullable(); // “〇〇→△△（経由 …）” 等の自由入力が欲しい場合
            $t->text('note')->nullable();         // 備考

            $t->timestamps();

            // パフォーマンス系
            $t->index(['route_declaration_id', 'dow']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table "route_details" add constraint "route_details_amount_non_negative" check ("amount" >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('route_details');
        Schema::dropIfExists('route_declarations');
    }
};

<?php

use App\Enums\ExpenseTripType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commute_patterns', function (Blueprint $t) {
            $t->id();

            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->timestamp('submitted_at')->nullable()->index();

            $t->string('closest_station');
            $t->string('train_line')->nullable();
            $t->date('valid_from')->index();
            $t->date('valid_to')->index();
            $t->text('reason')->nullable();

            $t->timestamps();

            $t->index(['user_id', 'valid_from', 'valid_to']);
        });

        Schema::create('commute_pattern_legs', function (Blueprint $t) {
            $t->id();

            $t->foreignId('commute_pattern_id')
                ->constrained('commute_patterns')
                ->cascadeOnDelete();

            $t->string('dow', 3)->index();
            $t->unsignedInteger('seq')->default(100);

            $t->string('station_from')->nullable();
            $t->string('station_to')->nullable();
            $t->text('note')->nullable();
            $t->unsignedInteger('cost')->default(0);
            $t->string('trip_type', 32)->default(ExpenseTripType::ROUND_TRIP->value);

            $t->timestamps();

            $t->index(['commute_pattern_id', 'dow', 'seq']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table "commute_pattern_legs" add constraint "commute_pattern_legs_seq_non_negative" check ("seq" >= 0)');
            DB::statement('alter table "commute_pattern_legs" add constraint "commute_pattern_legs_cost_non_negative" check ("cost" >= 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('commute_pattern_legs');
        Schema::dropIfExists('commute_patterns');
    }
};

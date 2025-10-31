<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('leave_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employment_term_id')->constrained()->cascadeOnDelete();//termsではなくterm。model名が単数の為。
            $table->date('start_date');           // 休職開始（当日を含む）
            $table->date('end_date');             // 休職終了（当日を含む）
            $table->string('reason')->nullable(); // 育休, 傷病など自由記述
            $table->timestamps();

            $table->index(['employment_term_id', 'start_date']);
            $table->index(['employment_term_id', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_periods');
    }
};

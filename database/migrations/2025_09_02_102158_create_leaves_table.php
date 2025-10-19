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
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // 期間（単日OK・連休OK）
            $table->date('start_date');
            $table->date('end_date')->nullable(); // nullなら単日扱い

           // 発生理由/メモ（遅刻の詳細理由、有給の任意理由など）
            $table->text('reason')->nullable();

            // 休暇の種別
            // paid=有給, absense_to_paid=欠席から有給へ, special=特別休暇(結婚/忌引など), absence=欠席, adjustment=調整, left_early=早退, late=遅刻, other=その他 
            $table->enum('kind', ['paid', 'absense_to_paid', 'special', 'absence', 'adjustment', 'left_early', 'late', 'other'])->index();

            // 会社としての扱い（免除/非免除/不明）
            $table->enum('excused', ['excused', 'unexcused'])->default('unexcused')->index();

            // Special、Otherの種類（例: wedding, bereavement, sick child leave 等）
            $table->string('special_type', 100)->nullable();

            // 時間帯（partial対応：遅刻・早退など。終日ならnull）
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();

            // 処理方法メモ（管理者用。給与計算などでの特記事項など）
            $table->text('handle_type')->nullable();

            // 承認ステータス（最低限）
            $table->enum('status', ['approved', 'pending', 'rejected', 'other'])->default('approved')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->index(['user_id', 'start_date', 'end_date', 'excused', 'kind']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leaves');
    }
};
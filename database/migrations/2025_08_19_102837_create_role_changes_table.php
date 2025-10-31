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
        Schema::create('role_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();       // 対象ユーザー
            $table->string('requested_role');                                      // 申請ロール
            $table->text('reason')->nullable();                                    // 理由
            $table->foreignId('requested_by_id')->constrained('users');           // 申請者（=通常は本人）
            $table->string('status')->default('pending');                          // pending|approved|denied
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_changes');
    }
};

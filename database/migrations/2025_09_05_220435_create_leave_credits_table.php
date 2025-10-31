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
        Schema::create('leave_credits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('fy', 8)->index();            // 例: 'FY2025'
            $t->decimal('granted_days', 5, 2)->default(0); // 付与
            $t->decimal('used_days', 5, 2)->default(0);    // 消化
            $t->timestamps();
            $t->unique(['user_id', 'fy']);
        });

        // 可逆な履歴（+付与 / -消化 / +戻し）
        Schema::create('leave_credit_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('leave_credit_id')->constrained()->cascadeOnDelete();
            $t->enum('type', ['grant', 'consume', 'revert', 'adjust'])->index();
            $t->decimal('days', 5, 2);       // 0.5 / 0.25 などOK
            $t->string('reason')->nullable(); // 'leave#123', 'annual grant', etc
            $t->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_credits');
        Schema::dropIfExists('leave_credit_transactions');
    }
};

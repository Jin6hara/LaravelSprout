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
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();
            // ポリモーフィック紐付け
            $table->morphs('approvable');                               // approvable_type, approvable_id
            $table->string('title');                                    // 例: "権限変更: general → admin"
            $table->foreignId('requested_by_id')->constrained('users');
            $table->string('current_state')->default('pending');        // pending|approved|denied
            $table->json('metadata')->nullable();                       // 承認フロー/対象者/補足など
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};

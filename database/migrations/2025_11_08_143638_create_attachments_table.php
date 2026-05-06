<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('attachments', function (Blueprint $t) {
            $t->id();
            $t->morphs('attachable');                // attachable_type, attachable_id
            $t->string('path');                      // storage パス（例：attachments/..）
            $t->string('original_name')->nullable();
            $t->unsignedBigInteger('size')->nullable();
            $t->timestamps();
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('alter table "attachments" add constraint "attachments_size_non_negative" check ("size" >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        Schema::create('lessons', function (Blueprint $t) {
            $t->id();
            $t->string('lesson_name');
            $t->string('lesson_code')->index();
            $t->unsignedSmallInteger('lesson_minute'); // 30/40/45など
            $t->enum('lesson_type', ['kids','Adults','Break','other'])->default('other');
            // timestamps不要
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};

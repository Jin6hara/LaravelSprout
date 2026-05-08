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
        // users テーブルに district_id / department_id を追加
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('district_id')->nullable()
                ->constrained('districts')->nullOnDelete();
            $table->foreignId('department_id')->nullable()
                ->constrained('departments')->nullOnDelete();
        });

        // schools テーブルに district_id を追加
        Schema::table('schools', function (Blueprint $table) {
            $table->foreignId('district_id')->nullable()
                ->constrained('districts')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schools', function (Blueprint $table) {
            $table->dropConstrainedForeignId('district_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('district_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_changes', function (Blueprint $table) {
            $table->unsignedBigInteger('district_id')->nullable()->after('reason');
            $table->unsignedBigInteger('department_id')->nullable()->after('district_id');
            $table->json('scopes')->nullable()->after('department_id');

            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('role_changes', function (Blueprint $table) {
            $table->dropForeign(['district_id']);
            $table->dropForeign(['department_id']);
            $table->dropColumn(['district_id', 'department_id', 'scopes']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_lines', function (Blueprint $t) {
            $t->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->index(['district_id', 'department_id']);
        });

        Schema::table('leaves', function (Blueprint $t) {
            $t->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->index(['district_id', 'department_id']);
        });

        Schema::table('events', function (Blueprint $t) {
            $t->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $t->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $t->index(['district_id', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $t) {
            $t->dropForeign(['district_id']);
            $t->dropForeign(['department_id']);
            $t->dropIndex(['district_id', 'department_id']);
            $t->dropColumn(['district_id', 'department_id']);
        });

        Schema::table('leaves', function (Blueprint $t) {
            $t->dropForeign(['district_id']);
            $t->dropForeign(['department_id']);
            $t->dropIndex(['district_id', 'department_id']);
            $t->dropColumn(['district_id', 'department_id']);
        });

        Schema::table('schedule_lines', function (Blueprint $t) {
            $t->dropForeign(['district_id']);
            $t->dropForeign(['department_id']);
            $t->dropIndex(['district_id', 'department_id']);
            $t->dropColumn(['district_id', 'department_id']);
        });
    }
};

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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('family_name');
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('name_in_kana')->nullable();
            $table->string('name'); // ←既存互換用、結合した値を保持
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->enum('gender', ['male', 'female', 'other', 'unknown'])->default('unknown');
            $table->string('profile_picture')->nullable();
            $table->text('note')->nullable();
            $table->softDeletes();
            // 交通費精算に関連する最低限の情報
            $table->string('employee_code', 6)->unique(); // 社員コード
            $table->string('address')->nullable(); // 自宅など出発地の参考に
            $table->string('phone_number')->nullable(); // 緊急連絡や照会用
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

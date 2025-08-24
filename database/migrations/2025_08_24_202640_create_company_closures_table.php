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
        Schema::create('company_closures', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('name'); // 例: 会社法定休日, 全社休業日 等
            $table->enum('type', ['statutory_off', 'company_off', 'special', 'emergency'])->default('company_off');
            $table->boolean('is_full_day')->default(true); // 半休日対応したい場合はfalse + time帯をmetaで
            $table->json('meta')->nullable(); // 例: { "start":"09:00","end":"13:00" }
            $table->timestamps();
            $table->unique(['date', 'type']); // 同日の重複登録防止
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_closures');
    }
};

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
        // 1) schools
        Schema::create('schools', function (Blueprint $t) {
            $t->id();
            $t->string('school_code')->unique();
            $t->string('school_name');
            $t->string('name_kana')->nullable();
            $t->json('aliases')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->index('school_name');
        });

        // 2) school_profiles
        Schema::create('school_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_id')->constrained()->cascadeOnDelete();
            $t->text('address');
            $t->text('description')->nullable();
            $t->string('map_image_path')->nullable();
            $t->dateTime('valid_from')->useCurrent();
            $t->dateTime('valid_to')->nullable(); // null=現行
            $t->timestamps();
            $t->index(['school_id', 'valid_to']);
        });

        // 3) school_stations
        Schema::create('school_stations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('school_profile_id')->constrained()->cascadeOnDelete();

            $t->string('station_name');             // 例: 学園前駅
            $t->string('line')->nullable();         // 例: 近鉄奈良線（任意）
            $t->unsignedTinyInteger('walk_minutes')->nullable(); // 例: 8（徒歩8分）
            $t->text('guide_text')->nullable();     // 駅からの道順
            $t->string('guide_image_path')->nullable();

            $t->unsignedSmallInteger('sort_order')->default(0); // 並び順（0,1,2,...）
            $t->timestamps();

            // 複数行OK
            $t->index(['school_profile_id', 'sort_order']);
            $t->index('station_name'); // 名前での簡易検索用（任意）
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
        Schema::dropIfExists('school_profiles');
        Schema::dropIfExists('school_stations');
    }
};

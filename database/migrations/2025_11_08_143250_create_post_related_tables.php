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
        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('type', 32)->default('announcement');            // ※app/Enums/PostType.phpと整合
            $t->foreignId('user_id')->constrained()->cascadeOnDelete(); // 投稿者
            $t->string('title')->nullable();
            $t->text('body');
            $t->timestamp('expires_at')->nullable(); // 有効期限
            $t->boolean('allow_replies')->default(true);
            $t->timestamps();

            $t->index(['user_id', 'created_at']);
        });

        Schema::create('post_user', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('confirmed_at')->nullable(); // 既読=時刻、未読=NULL
            $t->timestamps();

            $t->unique(['post_id', 'user_id']);
            $t->index(['user_id', 'post_id']);
            $t->index(['user_id', 'confirmed_at']);
        });

        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('post_id')->constrained('posts')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()
                ->constrained('comments')->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();

            $t->index(['post_id', 'parent_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('post_user');
        Schema::dropIfExists('posts');
    }
};

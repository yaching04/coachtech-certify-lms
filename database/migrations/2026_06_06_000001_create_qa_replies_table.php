<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 質問掲示板スレッドへの回答。テキスト本文のみ(添付・ネスト・ベスト回答は非対応)。
 *
 * 物理削除のみ。親スレッド削除時は cascade 削除する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_replies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('qa_thread_id')->constrained('qa_threads')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['qa_thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_replies');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講登録(Enrollment)単位の個人学習目標。受講生本人が CRUD し、達成日時(achieved_at)の有無で達成 / 未達成を表す。
 *
 * 物理削除のみ(履歴・論理削除なし)。親の受講登録が削除されたら cascade 削除する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_goals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->date('target_date')->nullable();
            $table->timestamp('achieved_at')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'achieved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_goals');
    }
};

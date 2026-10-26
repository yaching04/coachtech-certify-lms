<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 受講登録(Enrollment)単位のコーチメモ。コーチ / 管理者が時系列に残す内部業務記録で、受講生本人には一切見せない。
 *
 * 物理削除のみ(履歴・論理削除なし)。親の受講登録が物理削除されたら cascade 削除する(論理削除では残し、一覧側で除外する)。
 * 作成者(author_id)はユーザー物理削除時に null へ戻す(コーチ離任時の自動委譲・書き換えはしない)。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_notes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignUlid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['enrollment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_notes');
    }
};

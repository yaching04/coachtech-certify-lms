<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 運営お知らせ(admin による一斉配信)の配信履歴。配信は不可逆のため更新・削除は想定しない。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 200);
            $table->text('body');
            $table->string('target_type', 20);
            $table->foreignUlid('target_certification_id')->nullable()->constrained('certifications')->restrictOnDelete();
            $table->foreignUlid('target_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('dispatched_count')->default(0);
            $table->timestamp('dispatched_at');
            $table->foreignUlid('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('dispatched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};

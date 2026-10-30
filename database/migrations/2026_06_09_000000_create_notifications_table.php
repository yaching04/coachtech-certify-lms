<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * アプリ内通知(Laravel Database Notification)テーブル。
 *
 * 通知 1 件 = 受信者 1 人分。data(JSON)に notification_type / title / message / url を持ち、
 * 一覧・既読化・遷移先の決定はこの data だけで完結させる(元レコードが削除・非公開化されても一覧は壊れない)。
 * users の主キーが ULID のため、notifiable は ulidMorphs で定義する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->ulidMorphs('notifiable');
            $table->json('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // 一覧(新しい順)と未読件数バッジの検索用
            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

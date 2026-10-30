<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 面談リマインダーの送信記録。(面談, 受信者, タイミング) の UNIQUE 制約で、
 * リマインダー処理が二重起動・再実行されても同じ人へ同じリマインダーを 2 回送らないことを DB で保証する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_reminders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('window', 20);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id', 'window']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_reminders');
    }
};

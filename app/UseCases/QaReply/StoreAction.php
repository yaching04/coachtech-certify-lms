<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドへの回答投稿ユースケース。解決済スレッドへの追加回答も許可する。
 * 回答受信時の通知は本機能のスコープ外のため発火しない。
 */
final class StoreAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(User $author, QaThread $thread, array $validated): QaReply
    {
        return DB::transaction(fn () => QaReply::create([
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => $validated['body'],
        ]));
    }
}

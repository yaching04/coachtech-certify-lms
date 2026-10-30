<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドへの回答投稿ユースケース。解決済スレッドへの追加回答も許可する。
 *
 * 回答の保存が確定した後、スレッド投稿者へ「回答が付いた」通知を送る(解決済スレッドへの回答も同様)。
 * 通知はスレッド投稿者のみで、ほかの回答者には送らない。自分のスレッドへ自分で回答した場合は送らない。
 */
final class StoreAction
{
    public function __construct(private readonly NotificationDispatchService $notifier) {}

    /**
     * @param array{body: string} $validated
     */
    public function __invoke(User $author, QaThread $thread, array $validated): QaReply
    {
        $reply = DB::transaction(fn () => QaReply::create([
            'qa_thread_id' => $thread->id,
            'user_id' => $author->id,
            'body' => $validated['body'],
        ]));

        $threadAuthor = $thread->user;
        if ($threadAuthor !== null && $threadAuthor->id !== $author->id) {
            $reply->setRelation('thread', $thread)->setRelation('user', $author);
            $this->notifier->sendQuietly([$threadAuthor], new QaReplyReceivedNotification($reply));
        }

        return $reply;
    }
}

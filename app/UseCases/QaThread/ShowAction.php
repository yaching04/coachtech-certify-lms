<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;

/**
 * 質問スレッド詳細の取得ユースケース。投稿者・資格・回答(古い順、回答者付き)と回答件数をまとめて読み込む。
 */
final class ShowAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        return $thread
            ->load([
                'user',
                'certification',
                'replies' => fn ($q) => $q->orderBy('created_at')->orderBy('id'),
                'replies.user',
            ])
            ->loadCount('replies');
    }
}

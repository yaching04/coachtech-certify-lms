<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドの編集ユースケース。タイトル / 本文のみ更新し、資格・解決状態は変更しない。
 */
final class UpdateAction
{
    /**
     * @param array{title: string, body: string} $validated
     */
    public function __invoke(QaThread $thread, array $validated): QaThread
    {
        DB::transaction(fn () => $thread->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]));

        return $thread;
    }
}

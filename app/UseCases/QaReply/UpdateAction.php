<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

/**
 * 回答の編集ユースケース。本文のみ更新する。
 */
final class UpdateAction
{
    /**
     * @param array{body: string} $validated
     */
    public function __invoke(QaReply $reply, array $validated): QaReply
    {
        DB::transaction(fn () => $reply->update([
            'body' => $validated['body'],
        ]));

        return $reply;
    }
}

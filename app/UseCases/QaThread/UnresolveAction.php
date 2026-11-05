<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Exceptions\QaBoard\QaThreadInvalidTransitionException;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 解決済の質問スレッドを未解決に戻すユースケース(resolved → open、resolved_at をクリア)。
 */
final class UnresolveAction
{
    /**
     * @throws QaThreadInvalidTransitionException
     */
    public function __invoke(QaThread $thread): QaThread
    {
        if ($thread->status !== QaThreadStatus::Resolved) {
            throw QaThreadInvalidTransitionException::forUnresolve();
        }

        DB::transaction(fn () => $thread->update([
            'status' => QaThreadStatus::Open,
            'resolved_at' => null,
        ]));

        return $thread;
    }
}

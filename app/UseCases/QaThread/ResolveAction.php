<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Exceptions\QaBoard\QaThreadInvalidTransitionException;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドを解決済にするユースケース(open → resolved、resolved_at を記録)。
 */
final class ResolveAction
{
    /**
     * @throws QaThreadInvalidTransitionException
     */
    public function __invoke(QaThread $thread): QaThread
    {
        if ($thread->status !== QaThreadStatus::Open) {
            throw QaThreadInvalidTransitionException::forResolve();
        }

        DB::transaction(fn () => $thread->update([
            'status' => QaThreadStatus::Resolved,
            'resolved_at' => now(),
        ]));

        return $thread;
    }
}

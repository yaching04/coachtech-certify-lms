<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\UserRole;
use App\Exceptions\QaBoard\QaThreadNotDeletableException;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 質問スレッドの物理削除ユースケース(配下の回答は FK cascade で同時に削除)。
 *
 * 投稿者本人による削除は回答が 0 件のときのみ許可し、1 件以上付いていれば QaThreadNotDeletableException(409) を
 * throw する(他の受講生・コーチが書いた回答を巻き込んで消さないため)。管理者のモデレーション削除は回答の有無を問わない。
 */
final class DestroyAction
{
    /**
     * @throws QaThreadNotDeletableException
     */
    public function __invoke(User $actor, QaThread $thread): void
    {
        DB::transaction(function () use ($actor, $thread) {
            if ($actor->role !== UserRole::Admin && $thread->replies()->lockForUpdate()->exists()) {
                throw new QaThreadNotDeletableException;
            }

            $thread->delete();
        });
    }
}

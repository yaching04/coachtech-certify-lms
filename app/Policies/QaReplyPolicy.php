<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板の回答(QaReply)に対する認可ポリシー。
 *
 * - create: student / coach かつ親スレッドを閲覧可能(admin は回答不可)
 * - update: 回答者本人(admin 以外)
 * - delete: admin(モデレーション)または回答者本人
 *
 * update / delete は回答単体で判定するため、親スレッドの閲覧可否(担当外コーチ / 公開停止など)は
 * Controller / FormRequest 側で `view` を併せて確認する。
 */
class QaReplyPolicy
{
    public function __construct(private readonly QaThreadPolicy $threadPolicy) {}

    public function create(User $user, QaThread $thread): bool
    {
        if (! in_array($user->role, [UserRole::Student, UserRole::Coach], true)) {
            return false;
        }

        return $this->threadPolicy->view($user, $thread);
    }

    public function update(User $user, QaReply $reply): bool
    {
        return $user->role !== UserRole::Admin && $reply->user_id === $user->id;
    }

    public function delete(User $user, QaReply $reply): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $reply->user_id === $user->id;
    }
}

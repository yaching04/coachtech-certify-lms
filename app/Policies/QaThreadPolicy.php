<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

/**
 * 質問掲示板の質問スレッド(QaThread)に対する認可ポリシー。
 *
 * - viewAny: admin / coach / student(取得範囲は `QaThread::scopeVisibleTo` で絞る)
 * - view: admin は全件(公開停止中の資格を含む)、student は公開中資格のスレッド、coach は担当かつ公開中資格のスレッド
 * - create: student のみ
 * - update / resolve / unresolve: 投稿者本人(student)のみ。admin は編集・解決切替不可
 * - delete: admin(モデレーション)または投稿者本人
 *
 * 「回答が付いたら本人削除不可」「解決済 → 解決済の二重遷移不可」等の状態ルールは Policy ではなく
 * Action のドメイン例外(409)で表現する(ONBOARDING の Policy / Action 責務分担に従う)。
 */
class QaThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Admin, UserRole::Coach, UserRole::Student], true);
    }

    public function view(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        $thread->loadMissing('certification');
        if ($thread->certification?->status !== CertificationStatus::Published) {
            return false;
        }

        return match ($user->role) {
            UserRole::Student => true,
            UserRole::Coach => $user->assignedCertifications()
                ->where('certifications.id', $thread->certification_id)
                ->exists(),
            default => false,
        };
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function update(User $user, QaThread $thread): bool
    {
        return $this->isAuthor($user, $thread) && $this->view($user, $thread);
    }

    public function delete(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $this->isAuthor($user, $thread) && $this->view($user, $thread);
    }

    public function resolve(User $user, QaThread $thread): bool
    {
        return $this->isAuthor($user, $thread) && $this->view($user, $thread);
    }

    public function unresolve(User $user, QaThread $thread): bool
    {
        return $this->isAuthor($user, $thread) && $this->view($user, $thread);
    }

    private function isAuthor(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Student && $thread->user_id === $user->id;
    }
}

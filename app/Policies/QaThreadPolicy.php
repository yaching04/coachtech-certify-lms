<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

class QaThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Student, UserRole::Coach, UserRole::Admin], true);
    }

    public function view(User $user, QaThread $thread): bool
    {
        if ($user->role === UserRole::Admin) {
            return true;
        }

        // 公開中の資格のみ閲覧可。コーチは担当資格のみ
        if ($thread->certification?->status !== CertificationStatus::Published) {
            return false;
        }

        if ($user->role === UserRole::Coach) {
            return $user->assignedCertifications()->where('certifications.id', $thread->certification_id)->exists();
        }

        return $user->role === UserRole::Student;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function update(User $user, QaThread $thread): bool
    {
        return $user->id === $thread->user_id;
    }

    public function delete(User $user, QaThread $thread): bool
    {
        // 管理者は常に削除可。投稿者本人は回答が0件のときのみ削除可（仮置き。後でコーチ確認）
        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->id === $thread->user_id && $thread->replies()->doesntExist();
    }

    public function resolve(User $user, QaThread $thread): bool
    {
        return $user->id === $thread->user_id;
    }

    public function unresolve(User $user, QaThread $thread): bool
    {
        return $user->id === $thread->user_id;
    }
}

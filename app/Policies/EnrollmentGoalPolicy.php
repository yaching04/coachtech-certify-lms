<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 個人目標(EnrollmentGoal)に対する認可ポリシー。
 *
 * - create: 受講登録の持ち主である受講生本人のみ(coach / admin / 他受講生は不可)
 * - update / delete / markAchieved / unmarkAchieved: 目標を持つ受講登録の持ち主である受講生本人のみ
 *
 * 目標の「閲覧」は受講登録詳細画面(EnrollmentPolicy::view = 本人 / 担当コーチ / admin)に従うため、ここでは定義しない。
 * 削除済(SoftDelete)の受講登録配下の目標は操作不可。
 */
class EnrollmentGoalPolicy
{
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->isOwner($user, $enrollment);
    }

    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $this->ownsGoal($user, $goal);
    }

    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->ownsGoal($user, $goal);
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->ownsGoal($user, $goal);
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->ownsGoal($user, $goal);
    }

    private function ownsGoal(User $user, EnrollmentGoal $goal): bool
    {
        $goal->loadMissing('enrollment');

        return $goal->enrollment !== null && $this->isOwner($user, $goal->enrollment);
    }

    private function isOwner(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $enrollment->user_id === $user->id
            && ! $enrollment->trashed();
    }
}

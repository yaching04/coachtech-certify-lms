<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/**
 * コーチメモ(EnrollmentNote)に対する認可ポリシー。受講生は閲覧を含めすべて拒否する(業務記録のため)。
 *
 * - viewAny(一覧セクションの表示): 管理者 / 当該資格の担当コーチ
 * - create: 管理者(任意の受講登録) / 当該資格の担当コーチ
 * - update / delete: 管理者(任意のメモ) / 当該資格の担当コーチかつメモの作成者本人(他コーチのメモは不可)
 *
 * 親の受講登録が論理削除されている場合は、管理者を含め一覧・追加・編集・削除すべて不可(メモは一覧から除外される)。
 * 担当コーチの判定は EnrollmentPolicy と同じく certification.coaches リレーションで行う。
 */
class EnrollmentNotePolicy
{
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        return $this->canHandle($user, $enrollment);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->canHandle($user, $enrollment);
    }

    public function update(User $user, EnrollmentNote $note): bool
    {
        return $this->canModify($user, $note);
    }

    public function delete(User $user, EnrollmentNote $note): bool
    {
        return $this->canModify($user, $note);
    }

    private function canModify(User $user, EnrollmentNote $note): bool
    {
        $note->loadMissing('enrollment');
        $enrollment = $note->enrollment;

        if ($enrollment === null || ! $this->canHandle($user, $enrollment)) {
            return false;
        }

        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $note->author_id === $user->id,
            default => false,
        };
    }

    private function canHandle(User $user, Enrollment $enrollment): bool
    {
        if ($enrollment->trashed()) {
            return false;
        }

        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Coach => $this->isAssignedCoach($enrollment, $user),
            default => false,
        };
    }

    private function isAssignedCoach(Enrollment $enrollment, User $coach): bool
    {
        $enrollment->loadMissing('certification.coaches');

        return $enrollment->certification?->coaches->contains('id', $coach->id) ?? false;
    }
}

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\MeetingPack;
use App\Models\User;

/**
 * 面談パック(追加面談購入用 SKU マスタ)の認可ルール。admin のみ全操作可能、coach / student は全操作不可。
 *
 * 状態に依存する可否(公開中は削除不可 / 不正な順序の状態遷移不可)は Policy ではなく
 * 各 Action のドメインルール(HTTP 409)で判定する。
 */
class MeetingPackPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function update(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function delete(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function publish(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function archive(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function unarchive(User $auth, MeetingPack $plan): bool
    {
        return $auth->role === UserRole::Admin;
    }
}

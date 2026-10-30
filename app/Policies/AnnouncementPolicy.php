<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Announcement;
use App\Models\User;

/**
 * 運営お知らせの認可ルール。配信履歴の閲覧と配信(作成)は admin のみ。
 *
 * 配信は不可逆のため update / delete は定義せず、常に拒否される。
 */
class AnnouncementPolicy
{
    public function viewAny(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function view(User $auth, Announcement $announcement): bool
    {
        return $auth->role === UserRole::Admin;
    }

    public function create(User $auth): bool
    {
        return $auth->role === UserRole::Admin;
    }
}

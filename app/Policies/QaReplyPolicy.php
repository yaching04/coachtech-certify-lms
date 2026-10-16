<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\User;

class QaReplyPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, [UserRole::Student, UserRole::Coach], true);
    }

    public function update(User $user, QaReply $reply): bool
    {
        return $user->id === $reply->user_id;
    }

    public function delete(User $user, QaReply $reply): bool
    {
        return $user->role === UserRole::Admin || $user->id === $reply->user_id;
    }
}

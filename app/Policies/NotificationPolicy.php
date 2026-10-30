<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/**
 * アプリ内通知(DatabaseNotification)の認可。全ロール共通で、本人宛の通知のみ閲覧・既読化できる。
 */
class NotificationPolicy
{
    public function view(User $user, DatabaseNotification $notification): bool
    {
        return $this->isOwner($user, $notification);
    }

    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $this->isOwner($user, $notification);
    }

    private function isOwner(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->id;
    }
}

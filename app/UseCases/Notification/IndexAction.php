<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Http\Requests\Notification\IndexRequest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

/**
 * 本人宛の通知一覧(新しい順・20 件ずつ)を返すユースケース。
 * 卒業済の受講生も過去の通知を閲覧できるよう、利用状態による絞り込みは行わない。
 */
final class IndexAction
{
    public const PER_PAGE = 20;

    /**
     * @return LengthAwarePaginator<DatabaseNotification>
     */
    public function __invoke(User $user, string $tab): LengthAwarePaginator
    {
        return $user->notifications()
            ->when($tab === IndexRequest::TAB_UNREAD, fn ($query) => $query->whereNull('read_at'))
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }
}

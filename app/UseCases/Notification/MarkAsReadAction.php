<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知 1 件を既読にし、遷移先 URL を返すユースケース。
 *
 * 遷移先は通知作成時に data.url へ保存したアプリ内相対パス。遷移先の資格が非公開になった等で
 * 閲覧できなくなっていても既読化は行い、遷移先側の認可にそのまま任せる(取り消し・特別表示はしない)。
 */
final class MarkAsReadAction
{
    public function __invoke(DatabaseNotification $notification): string
    {
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        // 外部 URL へのオープンリダイレクトを避けるため、アプリ内の相対パスのみ遷移先として扱う
        if (is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return route('notifications.index', [], false);
    }
}

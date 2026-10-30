<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use Illuminate\Notifications\DatabaseNotification;

/**
 * 通知詳細ページ用に通知 1 件を返すユースケース。
 *
 * 詳細ページで本文全文を読んだ時点で既読とみなすため、未読なら既読にする
 * (メールのボタンから直接開いた場合も一覧の未読が残らないようにする)。
 */
final class ShowAction
{
    public function __invoke(DatabaseNotification $notification): DatabaseNotification
    {
        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return $notification;
    }
}

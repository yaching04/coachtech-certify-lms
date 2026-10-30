<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\AppNotification;
use Throwable;

/**
 * 業務イベント通知の送信窓口。送信先の絞り込みを 1 箇所に集約する。
 *
 * 通知を作るのは「受講中(in_progress)の受講生」と「有効(in_progress)なコーチ」のみ。
 * 管理者・招待中・卒業・退会のユーザーには新しい通知を作らない(卒業後も過去の通知一覧は閲覧できる)。
 */
final class NotificationDispatchService
{
    public function isEligible(User $user): bool
    {
        return in_array($user->role, [UserRole::Student, UserRole::Coach], true)
            && $user->status === UserStatus::InProgress
            && ! $user->trashed();
    }

    /**
     * 送信対象に該当する受信者へ通知を送り、送信した人数を返す。送信中の例外はそのまま呼び出し元へ伝える。
     *
     * @param iterable<User|null> $recipients
     */
    public function send(iterable $recipients, AppNotification $notification): int
    {
        return $this->deliver($recipients, $notification, quietly: false);
    }

    /**
     * 業務操作(回答投稿・予約・チャット送信など)に付随する通知用。業務データ確定後に呼び、
     * メール送信失敗などで業務操作そのものがエラーにならないよう、受信者ごとに例外をログへ記録して処理を続ける。
     *
     * @param iterable<User|null> $recipients
     */
    public function sendQuietly(iterable $recipients, AppNotification $notification): int
    {
        return $this->deliver($recipients, $notification, quietly: true);
    }

    /**
     * @param iterable<User|null> $recipients
     */
    private function deliver(iterable $recipients, AppNotification $notification, bool $quietly): int
    {
        $notifiedIds = [];

        foreach ($recipients as $recipient) {
            // 退会(SoftDelete)済でリレーションが null になった相手は送信対象外
            if ($recipient === null || isset($notifiedIds[$recipient->id]) || ! $this->isEligible($recipient)) {
                continue;
            }

            try {
                $recipient->notify($notification);
            } catch (Throwable $e) {
                if (! $quietly) {
                    throw $e;
                }
                report($e);

                continue;
            }

            $notifiedIds[$recipient->id] = true;
        }

        return count($notifiedIds);
    }
}

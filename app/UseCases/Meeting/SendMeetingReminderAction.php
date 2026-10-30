<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Console\Commands\Mentoring\SendMeetingRemindersCommand;
use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * 1 件の面談について、当事者(受講生・担当コーチ)へリマインダーを送るユースケース。送信した人数を返す。
 *
 * 二重配信防止:
 * - 受信者ごとにトランザクションを張り、面談行を lockForUpdate で取り直して「予約済みのまま」かを再確認する
 * - meeting_reminders に (面談, 受信者, タイミング) を INSERT し、UNIQUE 制約で既に記録済みなら送らない
 * - 送信に失敗したら記録ごとロールバックし、次回の定期実行で再送させる(自動リトライはしない)
 *
 * 管理者・卒業・退会などの受信者は NotificationDispatchService の判定で送信対象外とし、記録も残さない。
 *
 * @see SendMeetingRemindersCommand
 */
final class SendMeetingReminderAction
{
    public function __construct(private readonly NotificationDispatchService $notifier) {}

    public function __invoke(Meeting $meeting, MeetingReminderWindow $window): int
    {
        $meeting->loadMissing(['student', 'coach']);

        $sent = 0;
        foreach ([$meeting->student, $meeting->coach] as $recipient) {
            if ($recipient === null || ! $this->notifier->isEligible($recipient)) {
                continue;
            }

            try {
                if ($this->sendTo($meeting, $window, $recipient)) {
                    $sent++;
                }
            } catch (Throwable $e) {
                // 1 人の送信失敗で他の受信者・他の面談の処理を止めない。記録はロールバック済みなので次回再送される
                report($e);
            }
        }

        return $sent;
    }

    private function sendTo(Meeting $meeting, MeetingReminderWindow $window, User $recipient): bool
    {
        return DB::transaction(function () use ($meeting, $window, $recipient): bool {
            $locked = Meeting::query()->whereKey($meeting->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== MeetingStatus::Reserved) {
                return false;
            }

            $now = now();
            $inserted = MeetingReminder::query()->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'meeting_id' => $locked->id,
                'user_id' => $recipient->id,
                'window' => $window->value,
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($inserted === 0) {
                return false;
            }

            $locked->setRelation('student', $meeting->student)->setRelation('coach', $meeting->coach);

            return $this->notifier->send([$recipient], new MeetingReminderNotification($locked, $window, $recipient)) > 0;
        });
    }
}

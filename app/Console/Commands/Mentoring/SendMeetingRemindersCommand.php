<?php

declare(strict_types=1);

namespace App\Console\Commands\Mentoring;

use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\UseCases\Meeting\SendMeetingReminderAction;
use Illuminate\Console\Command;

/**
 * 予約済み面談の事前リマインダー(前日 / 開始 1 時間前)を当事者へ配信する Schedule Command。
 *
 * - --window=eve: 毎日 18:00 に起動し、翌日に開始する面談が対象
 * - --window=one_hour_before: 15 分間隔で起動し、開始時刻が「今 〜 60 分後」の面談が対象
 *
 * 同じ面談が複数回の起動で対象に入っても、SendMeetingReminderAction が送信記録の UNIQUE 制約で二重配信を防ぐ(冪等)。
 */
class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders
        {--window= : 配信タイミング(eve = 前日 / one_hour_before = 1 時間前)}';

    protected $description = '予約済みの面談のリマインダー通知(前日 / 開始 1 時間前)を受講生・コーチへ配信する';

    public function handle(SendMeetingReminderAction $action): int
    {
        $window = MeetingReminderWindow::tryFrom((string) $this->option('window'));
        if ($window === null) {
            $this->error('--window には eve または one_hour_before を指定してください。');

            return self::INVALID;
        }

        [$from, $to] = $window->range(now());
        $meetingCount = 0;
        $sentCount = 0;

        Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->where('scheduled_at', '>', $from)
            ->where('scheduled_at', '<=', $to)
            ->with(['student', 'coach'])
            ->chunkById(100, function ($meetings) use ($action, $window, &$meetingCount, &$sentCount): void {
                foreach ($meetings as $meeting) {
                    $sentCount += $action($meeting, $window);
                    $meetingCount++;
                }
            });

        $this->info("{$window->label()}リマインダー: 対象の面談 {$meetingCount} 件 / 送信 {$sentCount} 件");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use App\Models\MeetingReminder;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 面談リマインダー(S-B-09)。
 *
 * - eve: 翌日に開始する予約済み面談 / one_hour_before: 開始が「今 〜 60 分後」の予約済み面談が対象
 * - 受講生・担当コーチの両方へ アプリ内通知 + メール で送る(受信者の利用状態によっては送らない)
 * - 二重起動・再実行しても同じリマインダーは 1 回だけ。送信失敗時は記録を残さず次回再送
 */
class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $coach;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->student()->inProgress()->create(['name' => '受講 花子']);
        $this->coach = User::factory()->coach()->inProgress()->create(['name' => 'コーチ 太郎']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function meeting(Carbon $at, string $state = 'reserved', ?User $student = null, ?User $coach = null): Meeting
    {
        return Meeting::factory()->{$state}()
            ->forCoach($coach ?? $this->coach)
            ->forStudent($student ?? $this->student)
            ->create(['scheduled_at' => $at]);
    }

    public function test_eve_targets_reserved_meetings_starting_tomorrow_and_notifies_both_parties(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-10 18:00:00'));

        $tomorrowFirst = $this->meeting(Carbon::parse('2026-11-11 00:00:00'));
        $tomorrowLast = $this->meeting(Carbon::parse('2026-11-11 23:00:00'));
        $today = $this->meeting(Carbon::parse('2026-11-10 20:00:00'));
        $dayAfter = $this->meeting(Carbon::parse('2026-11-12 00:00:00'));
        $canceled = $this->meeting(Carbon::parse('2026-11-11 10:00:00'), 'canceled');
        $completed = $this->meeting(Carbon::parse('2026-11-11 11:00:00'), 'completed');

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])
            ->expectsOutput('前日リマインダー: 対象の面談 2 件 / 送信 4 件')
            ->assertExitCode(0);

        foreach ([$this->student, $this->coach] as $user) {
            Notification::assertSentToTimes($user, MeetingReminderNotification::class, 2);
            Notification::assertSentTo(
                $user,
                MeetingReminderNotification::class,
                fn (MeetingReminderNotification $n, array $channels) => $channels === ['database', 'mail']
            );
        }
        $reminded = MeetingReminder::query()->pluck('meeting_id')->unique()->sort()->values()->all();
        $expected = collect([$tomorrowFirst->id, $tomorrowLast->id])->sort()->values()->all();
        $this->assertSame($expected, $reminded);
        foreach ([$today, $dayAfter, $canceled, $completed] as $excluded) {
            $this->assertDatabaseMissing('meeting_reminders', ['meeting_id' => $excluded->id]);
        }
    }

    public function test_one_hour_before_targets_meetings_starting_within_the_next_60_minutes(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-10 10:00:00'));

        $inOneHour = $this->meeting(Carbon::parse('2026-11-10 11:00:00'));
        $startedNow = $this->meeting(Carbon::parse('2026-11-10 10:00:00'));
        $tooFar = $this->meeting(Carbon::parse('2026-11-10 12:00:00'));
        $canceled = $this->meeting(Carbon::parse('2026-11-10 11:00:00'), 'canceled', coach: User::factory()->coach()->inProgress()->create());

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])
            ->expectsOutput('1 時間前リマインダー: 対象の面談 1 件 / 送信 2 件')
            ->assertExitCode(0);

        $this->assertDatabaseHas('meeting_reminders', ['meeting_id' => $inOneHour->id, 'user_id' => $this->student->id, 'window' => 'one_hour_before']);
        $this->assertDatabaseHas('meeting_reminders', ['meeting_id' => $inOneHour->id, 'user_id' => $this->coach->id, 'window' => 'one_hour_before']);
        foreach ([$startedNow, $tooFar, $canceled] as $excluded) {
            $this->assertDatabaseMissing('meeting_reminders', ['meeting_id' => $excluded->id]);
        }
    }

    public function test_rerunning_or_overlapping_runs_do_not_send_duplicates(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-10 09:00:00'));
        $this->meeting(Carbon::parse('2026-11-10 10:00:00'));

        // 15 分間隔の起動で同じ面談が 2 回窓に入るケース + 手動再実行
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])->assertExitCode(0);
        $this->travelTo(Carbon::parse('2026-11-10 09:15:00'));
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])
            ->expectsOutput('1 時間前リマインダー: 対象の面談 1 件 / 送信 0 件')
            ->assertExitCode(0);
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])->assertExitCode(0);

        Notification::assertSentToTimes($this->student, MeetingReminderNotification::class, 1);
        Notification::assertSentToTimes($this->coach, MeetingReminderNotification::class, 1);
        $this->assertSame(2, MeetingReminder::count());
    }

    public function test_eve_and_one_hour_before_are_delivered_independently(): void
    {
        Notification::fake();
        $meeting = $this->meeting(Carbon::parse('2026-11-11 10:00:00'));

        $this->travelTo(Carbon::parse('2026-11-10 18:00:00'));
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])->assertExitCode(0);
        $this->travelTo(Carbon::parse('2026-11-11 09:00:00'));
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])->assertExitCode(0);

        Notification::assertSentToTimes($this->student, MeetingReminderNotification::class, 2);
        Notification::assertSentToTimes($this->coach, MeetingReminderNotification::class, 2);
        $this->assertSame(
            ['eve', 'one_hour_before'],
            MeetingReminder::where('meeting_id', $meeting->id)->where('user_id', $this->student->id)
                ->get()->map(fn (MeetingReminder $r) => $r->window->value)->sort()->values()->all()
        );
    }

    public function test_ineligible_recipient_is_skipped_without_record(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-10 09:00:00'));
        $graduated = User::factory()->student()->graduated()->create();
        $withdrawnCoach = User::factory()->coach()->inProgress()->create();
        $this->meeting(Carbon::parse('2026-11-10 10:00:00'), student: $graduated);
        $this->meeting(Carbon::parse('2026-11-10 09:30:00'), coach: $withdrawnCoach);
        $withdrawnCoach->delete();

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])
            ->expectsOutput('1 時間前リマインダー: 対象の面談 2 件 / 送信 2 件')
            ->assertExitCode(0);

        Notification::assertNotSentTo($graduated, MeetingReminderNotification::class);
        Notification::assertSentToTimes($this->coach, MeetingReminderNotification::class, 1);
        Notification::assertSentToTimes($this->student, MeetingReminderNotification::class, 1);
        $this->assertDatabaseMissing('meeting_reminders', ['user_id' => $graduated->id]);
        $this->assertDatabaseMissing('meeting_reminders', ['user_id' => $withdrawnCoach->id]);
    }

    public function test_stores_in_app_notification_with_counterpart_name_and_link_to_meeting(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 18:00:00'));
        $meeting = $this->meeting(Carbon::parse('2026-11-11 10:00:00'));

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])->assertExitCode(0);

        $studentData = DatabaseNotification::where('notifiable_id', $this->student->id)->sole()->data;
        $this->assertSame('meeting_reminder', $studentData['notification_type']);
        $this->assertSame('明日は面談の予定があります', $studentData['title']);
        $this->assertSame('2026/11/11(水) 10:00 〜 コーチ 太郎 さんとの面談', $studentData['message']);
        $this->assertSame(route('meetings.show', $meeting, false), $studentData['url']);

        $coachData = DatabaseNotification::where('notifiable_id', $this->coach->id)->sole()->data;
        $this->assertStringContainsString('受講 花子 さんとの面談', $coachData['message']);
    }

    public function test_failed_delivery_is_not_recorded_and_is_retried_on_next_run(): void
    {
        $this->travelTo(Carbon::parse('2026-11-10 09:00:00'));
        $this->meeting(Carbon::parse('2026-11-10 10:00:00'));

        // メール送信が失敗する状態(未定義の mailer)
        config(['mail.default' => 'broken']);
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])
            ->expectsOutput('1 時間前リマインダー: 対象の面談 1 件 / 送信 0 件')
            ->assertExitCode(0);
        $this->assertSame(0, MeetingReminder::count());
        $this->assertSame(0, DatabaseNotification::count());

        config(['mail.default' => 'array']);
        $this->travelTo(Carbon::parse('2026-11-10 09:15:00'));
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])
            ->expectsOutput('1 時間前リマインダー: 対象の面談 1 件 / 送信 2 件')
            ->assertExitCode(0);
        $this->assertSame(2, MeetingReminder::count());
        $this->assertSame(2, DatabaseNotification::count());
    }

    public function test_invalid_window_option_fails_without_sending(): void
    {
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-10 09:00:00'));
        $this->meeting(Carbon::parse('2026-11-10 10:00:00'));

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'tomorrow'])->assertExitCode(2);
        $this->artisan('notifications:send-meeting-reminders')->assertExitCode(2);

        Notification::assertNothingSent();
    }

    public function test_both_windows_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn (Event $e) => str_contains((string) $e->command, 'notifications:send-meeting-reminders'))
            ->mapWithKeys(fn (Event $e) => [str_contains((string) $e->command, '--window=eve') ? 'eve' : 'one_hour_before' => $e->expression]);

        $this->assertSame('0 18 * * *', $events['eve']);
        $this->assertSame('*/15 * * * *', $events['one_hour_before']);
        $this->assertSame(['eve', 'one_hour_before'], collect(MeetingReminderWindow::cases())->map->value->all());
    }
}

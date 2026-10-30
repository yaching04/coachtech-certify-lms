<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Meeting;

use App\Models\Certification;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 面談の予約 / キャンセル時の通知(S-B-04)。
 *
 * - 予約時は担当コーチへ通知する(受講生本人には送らない)
 * - キャンセル時はキャンセルしなかった側へ通知する
 * - 通知先が退会済なら送らない
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_notifies_coach(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 3]);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create(['meeting_url' => 'https://meet.example.com/x']);
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);

        $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d\TH:i:s'),
            'topic' => '相談したい',
        ])->assertRedirect();

        $meeting = Meeting::sole();
        Notification::assertSentTo(
            $coach,
            MeetingReservedNotification::class,
            fn (MeetingReservedNotification $n, array $channels) => $channels === ['database', 'mail']
                && $n->toArray($coach)['url'] === route('meetings.show', $meeting, false)
        );
        Notification::assertNotSentTo($student, MeetingReservedNotification::class);
        Notification::assertNotSentTo($admin, MeetingReservedNotification::class);
    }

    public function test_student_cancel_notifies_coach(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $this->actingAs($student)->post(route('meetings.cancel', $meeting))->assertRedirect();

        Notification::assertSentTo(
            $coach,
            MeetingCanceledNotification::class,
            fn (MeetingCanceledNotification $n) => str_contains($n->toArray($coach)['message'], $student->name)
        );
        Notification::assertNotSentTo($student, MeetingCanceledNotification::class);
    }

    public function test_coach_cancel_notifies_student(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->inProgress()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $this->actingAs($coach)->post(route('meetings.cancel', $meeting))->assertRedirect();

        Notification::assertSentTo($student, MeetingCanceledNotification::class);
        Notification::assertNotSentTo($coach, MeetingCanceledNotification::class);
    }

    public function test_cancel_does_not_notify_withdrawn_coach(): void
    {
        Notification::fake();
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->withdrawn()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);

        $this->actingAs($student)->post(route('meetings.cancel', $meeting))->assertRedirect();

        Notification::assertNothingSent();
    }
}

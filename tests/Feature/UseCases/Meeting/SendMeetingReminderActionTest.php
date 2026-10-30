<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\SendMeetingReminderAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendMeetingReminderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_meeting_canceled_after_selection_is_not_reminded(): void
    {
        Notification::fake();
        $meeting = Meeting::factory()->reserved()
            ->forCoach(User::factory()->coach()->inProgress()->create())
            ->forStudent(User::factory()->student()->inProgress()->create())
            ->create();

        // コマンドが対象を読み込んだ後、送信前にキャンセルされたケース(メモリ上の $meeting は reserved のまま)
        Meeting::whereKey($meeting->id)->update(['status' => MeetingStatus::Canceled->value, 'canceled_at' => now()]);

        $sent = app(SendMeetingReminderAction::class)($meeting, MeetingReminderWindow::Eve);

        $this->assertSame(0, $sent);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('meeting_reminders', 0);
    }
}

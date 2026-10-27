<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Meeting;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingQuotaTransaction;
use App\Models\User;
use App\Services\MeetingQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-B-10: 面談キャンセル時に消費した面談回数が残数へ返却されることを検証する。
 */
class CancelRefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cancel_increases_remaining_quota_by_one(): void
    {
        [$student, $coach, $meeting] = $this->reservedMeetingWithConsumedQuota();
        $quota = app(MeetingQuotaService::class);
        $before = $quota->remaining($student);

        $this->actingAs($student)->post(route('meetings.cancel', $meeting))->assertRedirect();

        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
        $this->assertSame($before + 1, $quota->remaining($student));
    }

    public function test_coach_cancel_refunds_quota_to_the_student(): void
    {
        [$student, $coach, $meeting] = $this->reservedMeetingWithConsumedQuota();
        $quota = app(MeetingQuotaService::class);
        $before = $quota->remaining($student);

        $this->actingAs($coach)->post(route('meetings.cancel', $meeting))->assertRedirect();

        $this->assertSame($before + 1, $quota->remaining($student));
        $this->assertDatabaseHas('meeting_quota_transactions', [
            'user_id' => $student->id,
            'related_meeting_id' => $meeting->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
            'amount' => 1,
        ]);
        $this->assertDatabaseMissing('meeting_quota_transactions', [
            'user_id' => $coach->id,
            'type' => MeetingQuotaTransactionType::Refunded->value,
        ]);
    }

    public function test_cancel_creates_exactly_one_refund_even_if_requested_twice(): void
    {
        [$student, $coach, $meeting] = $this->reservedMeetingWithConsumedQuota();

        $this->actingAs($student)->post(route('meetings.cancel', $meeting));
        $this->actingAs($student)->post(route('meetings.cancel', $meeting));

        $this->assertSame(1, MeetingQuotaTransaction::query()
            ->where('related_meeting_id', $meeting->id)
            ->where('type', MeetingQuotaTransactionType::Refunded->value)
            ->count());
    }

    public function test_cancel_rejected_for_started_meeting_does_not_refund(): void
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subHour(),
        ]);
        MeetingQuotaTransaction::factory()->consumed($meeting->id)->create(['user_id' => $student->id]);
        $before = app(MeetingQuotaService::class)->remaining($student);

        $this->actingAs($student)->post(route('meetings.cancel', $meeting));

        $this->assertSame(MeetingStatus::Reserved, $meeting->fresh()->status);
        $this->assertSame($before, app(MeetingQuotaService::class)->remaining($student));
    }

    /**
     * @return array{0: User, 1: User, 2: Meeting}
     */
    private function reservedMeetingWithConsumedQuota(): array
    {
        $student = User::factory()->student()->inProgress()->create(['max_meetings' => 5]);
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->addDays(3)->startOfHour(),
        ]);
        MeetingQuotaTransaction::factory()->consumed($meeting->id)->create(['user_id' => $student->id]);

        return [$student, $coach, $meeting];
    }
}

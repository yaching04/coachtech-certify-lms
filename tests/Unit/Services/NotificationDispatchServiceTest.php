<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\QaReply;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use App\Services\NotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 通知対象の判定と配信(S-B-04)。
 *
 * 対象は「受講中の受講生」と「有効なコーチ」のみ。管理者・招待中・卒業・退会・論理削除済は送らない。
 */
class NotificationDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_eligibility_matrix(): void
    {
        $service = new NotificationDispatchService;

        $this->assertTrue($service->isEligible(User::factory()->student()->inProgress()->create()));
        $this->assertTrue($service->isEligible(User::factory()->coach()->inProgress()->create()));
        $this->assertFalse($service->isEligible(User::factory()->admin()->create()));
        $this->assertFalse($service->isEligible(User::factory()->student()->invited()->create()));
        $this->assertFalse($service->isEligible(User::factory()->student()->graduated()->create()));
        $this->assertFalse($service->isEligible(User::factory()->student()->withdrawn()->create()));
        $this->assertFalse($service->isEligible(User::factory()->coach()->withdrawn()->create()));

        $deleted = User::factory()->student()->inProgress()->create();
        $deleted->delete();
        $this->assertFalse($service->isEligible($deleted));
    }

    public function test_send_skips_ineligible_null_and_duplicate_recipients(): void
    {
        Notification::fake();
        $service = new NotificationDispatchService;
        $student = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $reply = QaReply::factory()->create();

        $count = $service->send([$student, $student, null, $admin], new QaReplyReceivedNotification($reply));

        $this->assertSame(1, $count);
        Notification::assertSentToTimes($student, QaReplyReceivedNotification::class, 1);
        Notification::assertNotSentTo($admin, QaReplyReceivedNotification::class);
    }
}

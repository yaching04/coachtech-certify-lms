<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /notifications/{notification}`(通知詳細)と、運営お知らせ通知の遷移・メール内容の検証。
 */
class ShowTest extends TestCase
{
    use RefreshDatabase;

    private function announce(User $student, string $body = "1 行目の本文\n2 行目の本文"): Announcement
    {
        $announcement = Announcement::factory()->create(['title' => '重要なお知らせ', 'body' => $body]);
        $student->notify(new AdminAnnouncementNotification($announcement));

        return $announcement;
    }

    public function test_recipient_can_read_full_body_and_it_becomes_read(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $longBody = str_repeat('長い本文です。', 30).'最後の一文';
        $this->announce($student, $longBody);
        $notification = $student->notifications()->sole();

        $this->actingAs($student)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('重要なお知らせ')
            ->assertSee('最後の一文')
            ->assertSee('運営からのお知らせ');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_other_user_cannot_open_notification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $this->announce($student);
        $notification = $student->notifications()->sole();

        $this->actingAs(User::factory()->student()->inProgress()->create())
            ->get(route('notifications.show', $notification))
            ->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('notifications.show', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_graduated_recipient_can_still_open_detail(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $this->announce($student);
        $student->update(['status' => UserStatus::Graduated]);

        $this->actingAs($student)
            ->get(route('notifications.show', $student->notifications()->sole()))
            ->assertOk();
    }

    public function test_clicking_announcement_in_list_goes_to_detail(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $this->announce($student);
        $notification = $student->notifications()->sole();

        $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification))
            ->assertRedirect(route('notifications.show', $notification, false));
    }

    public function test_mail_contains_full_body_and_link_to_detail(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $announcement = Announcement::factory()->create(['title' => '重要なお知らせ', 'body' => "1 行目\n2 行目"]);
        $notification = new AdminAnnouncementNotification($announcement);
        $notification->id = 'test-notification-id';

        $mail = $notification->toMail($student);

        $this->assertSame('【Certify LMS】重要なお知らせ', $mail->subject);
        $this->assertContains('1 行目', $mail->introLines);
        $this->assertContains('2 行目', $mail->introLines);
        $this->assertSame(route('notifications.show', 'test-notification-id'), $mail->actionUrl);
    }
}

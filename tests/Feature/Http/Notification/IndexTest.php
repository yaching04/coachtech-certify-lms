<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Enums\NotificationType;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `GET /notifications`(通知一覧)と `POST /notifications/read-all`(全件既読)の検証。
 *
 * - 自分宛ての通知だけを新しい順に表示する
 * - 未読タブは未読だけに絞り込む / 不正な tab は 422 相当(バリデーションエラー)
 * - 20 件/ページでページネーションする
 * - 卒業生も一覧を閲覧できる(B-B-16)
 * - 全件既読は自分の未読だけを既読にする
 */
class IndexTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, string $title, ?CarbonInterface $readAt = null, ?CarbonInterface $createdAt = null): DatabaseNotification
    {
        $createdAt ??= now();

        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\QaReplyReceivedNotification',
            'data' => [
                'notification_type' => NotificationType::QaReplyReceived->value,
                'title' => $title,
                'message' => '本文',
                'url' => '/qa-board/dummy',
            ],
            'read_at' => $readAt,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }

    public function test_lists_only_own_notifications_newest_first(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $this->createNotification($student, '古い通知', null, now()->subDay());
        $this->createNotification($student, '新しい通知', now(), now());
        $this->createNotification($other, '他人の通知');

        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewHas('tab', 'all')
            ->assertViewHas('unreadCount', 1)
            ->assertSeeInOrder(['新しい通知', '古い通知'])
            ->assertDontSee('他人の通知');
    }

    public function test_unread_tab_shows_only_unread(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $this->createNotification($coach, '未読の通知');
        $this->createNotification($coach, '既読の通知', now());

        $this->actingAs($coach)
            ->get(route('notifications.index', ['tab' => 'unread']))
            ->assertOk()
            ->assertViewHas('tab', 'unread')
            ->assertSee('未読の通知')
            ->assertDontSee('既読の通知');
    }

    public function test_invalid_tab_is_rejected(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->get(route('notifications.index', ['tab' => 'foo']))
            ->assertSessionHasErrors('tab');
    }

    public function test_paginates_twenty_per_page(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        for ($i = 0; $i < 21; $i++) {
            $this->createNotification($student, "通知{$i}", null, now()->subMinutes($i));
        }

        $this->actingAs($student)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewHas('notifications', fn ($paginator) => $paginator->count() === 20 && $paginator->total() === 21);

        $this->actingAs($student)
            ->get(route('notifications.index', ['page' => 2]))
            ->assertOk()
            ->assertViewHas('notifications', fn ($paginator) => $paginator->count() === 1);
    }

    public function test_graduated_student_can_view_notifications(): void
    {
        $graduated = User::factory()->student()->graduated()->create();
        $this->createNotification($graduated, '卒業前の通知');

        $this->actingAs($graduated)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('卒業前の通知');
    }

    public function test_admin_can_open_notification_page(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('notifications.index'))
            ->assertOk();
    }

    public function test_mark_all_as_read_updates_only_own_unread(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $mine1 = $this->createNotification($student, 'a');
        $mine2 = $this->createNotification($student, 'b');
        $others = $this->createNotification($other, 'c');

        $this->actingAs($student)
            ->post(route('notifications.markAllAsRead'))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', 'すべての通知を既読にしました。');

        $this->assertNotNull($mine1->fresh()->read_at);
        $this->assertNotNull($mine2->fresh()->read_at);
        $this->assertNull($others->fresh()->read_at);
    }
}

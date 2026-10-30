<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * `POST /notifications/{notification}/read`(個別既読 + 遷移)の検証。
 *
 * - 自分の通知は既読にして data.url へリダイレクトする
 * - 他人の通知は 403 で既読にならない
 * - url が無い / 外部 URL の場合は通知一覧へ戻す(オープンリダイレクト防止)
 * - 遷移先が公開停止になっていても既読にはなり、遷移先の閲覧可否は遷移先の認可に従う
 */
class MarkAsReadTest extends TestCase
{
    use RefreshDatabase;

    private function createNotification(User $user, ?string $url): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => QaReplyReceivedNotification::class,
            'data' => [
                'notification_type' => 'qa_reply_received',
                'title' => 't',
                'message' => 'm',
                'url' => $url,
            ],
        ]);
    }

    public function test_marks_own_notification_as_read_and_redirects_to_url(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($student, '/qa-board/abc#reply-1');

        $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification))
            ->assertRedirect('/qa-board/abc#reply-1');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_mark_others_notification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();
        $notification = $this->createNotification($other, '/qa-board/abc');

        $this->actingAs($student)
            ->post(route('notifications.markAsRead', $notification))
            ->assertForbidden();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_falls_back_to_index_when_url_is_missing_or_external(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $noUrl = $this->createNotification($student, null);
        $external = $this->createNotification($student, 'https://evil.example.com/');
        $protocolRelative = $this->createNotification($student, '//evil.example.com/');

        foreach ([$noUrl, $external, $protocolRelative] as $notification) {
            $this->actingAs($student)
                ->post(route('notifications.markAsRead', $notification))
                ->assertRedirect(route('notifications.index', [], false));
            $this->assertNotNull($notification->fresh()->read_at);
        }
    }

    public function test_unknown_notification_returns_404(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)
            ->post(route('notifications.markAsRead', (string) Str::uuid()))
            ->assertNotFound();
    }

    public function test_notification_for_unpublished_target_is_marked_read_but_target_stays_inaccessible(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $replier = User::factory()->student()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->forCertification($certification)->byUser($student)->create();
        $reply = QaReply::factory()->create(['qa_thread_id' => $thread->id, 'user_id' => $replier->id]);
        $student->notify(new QaReplyReceivedNotification($reply->load('thread', 'user')));
        $notification = $student->notifications()->sole();

        $certification->update(['status' => CertificationStatus::Archived]);

        $response = $this->actingAs($student)->post(route('notifications.markAsRead', $notification));

        $response->assertRedirect(route('qa-board.show', $thread, false).'#reply-'.$reply->id);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->actingAs($student)->get(route('qa-board.show', $thread))->assertForbidden();
    }
}

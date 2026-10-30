<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * 質問掲示板の回答投稿時の通知(S-B-04)。
 *
 * - スレッド投稿者にだけ通知する(過去の回答者には通知しない)
 * - 解決済スレッドへの回答も通知する
 * - 自分のスレッドへの自分の回答は通知しない
 * - 投稿者が卒業 / 退会済なら通知しない
 * - アプリ内(database)とメール(mail)の両チャネルで送る
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_notifies_thread_author_via_database_and_mail(): void
    {
        Notification::fake();
        $author = User::factory()->student()->inProgress()->create();
        $replier = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->byUser($author)->create();

        $this->actingAs($replier)
            ->post(route('qa-board.replies.store', $thread), ['body' => 'こう解きます'])
            ->assertRedirect();

        $reply = QaReply::sole();
        Notification::assertSentTo(
            $author,
            QaReplyReceivedNotification::class,
            function (QaReplyReceivedNotification $notification, array $channels) use ($author, $thread, $reply) {
                $data = $notification->toArray($author);

                return $channels === ['database', 'mail']
                    && $data['notification_type'] === 'qa_reply_received'
                    && str_contains($data['title'], $thread->title)
                    && $data['url'] === route('qa-board.show', $thread, false).'#reply-'.$reply->id;
            }
        );
        Notification::assertNotSentTo($replier, QaReplyReceivedNotification::class);
    }

    public function test_reply_to_resolved_thread_also_notifies_author(): void
    {
        Notification::fake();
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->resolved()->byUser($author)->create();

        $this->actingAs(User::factory()->student()->inProgress()->create())
            ->post(route('qa-board.replies.store', $thread), ['body' => '補足です']);

        Notification::assertSentTo($author, QaReplyReceivedNotification::class);
    }

    public function test_self_reply_does_not_notify(): void
    {
        Notification::fake();
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->byUser($author)->create();

        $this->actingAs($author)
            ->post(route('qa-board.replies.store', $thread), ['body' => '自己解決しました']);

        Notification::assertNothingSent();
    }

    public function test_previous_repliers_are_not_notified(): void
    {
        Notification::fake();
        $author = User::factory()->student()->inProgress()->create();
        $previousReplier = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->byUser($author)->create();
        QaReply::factory()->create(['qa_thread_id' => $thread->id, 'user_id' => $previousReplier->id]);

        $this->actingAs(User::factory()->student()->inProgress()->create())
            ->post(route('qa-board.replies.store', $thread), ['body' => '別解です']);

        Notification::assertSentTo($author, QaReplyReceivedNotification::class);
        Notification::assertNotSentTo($previousReplier, QaReplyReceivedNotification::class);
    }

    public function test_coach_reply_notifies_student_author(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->forCertification($certification)->byUser($author)->create();

        $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $thread), ['body' => 'コーチの回答'])
            ->assertRedirect();

        Notification::assertSentTo($author, QaReplyReceivedNotification::class);
        Notification::assertNotSentTo($admin, QaReplyReceivedNotification::class);
    }

    public function test_graduated_or_withdrawn_author_is_not_notified(): void
    {
        Notification::fake();
        $graduated = User::factory()->student()->graduated()->create();
        $withdrawn = User::factory()->student()->withdrawn()->create();
        $replier = User::factory()->student()->inProgress()->create();

        foreach ([$graduated, $withdrawn] as $author) {
            $thread = QaThread::factory()->byUser($author)->create();
            $this->actingAs($replier)
                ->post(route('qa-board.replies.store', $thread), ['body' => '回答'])
                ->assertRedirect();
        }

        Notification::assertNothingSent();
        $this->assertSame(2, QaReply::count());
    }

    public function test_reply_creates_database_notification_row(): void
    {
        $author = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->byUser($author)->create();

        $this->actingAs(User::factory()->student()->inProgress()->create())
            ->post(route('qa-board.replies.store', $thread), ['body' => 'DB 行の確認']);

        $notification = $author->notifications()->sole();
        $this->assertNull($notification->read_at);
        $this->assertSame('qa_reply_received', $notification->data['notification_type']);
    }
}

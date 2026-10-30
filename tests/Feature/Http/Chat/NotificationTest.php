<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Chat;

use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\ChatMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * チャット送信時の通知(S-B-04)。
 *
 * - 送信者以外のルームメンバーへ通知する
 * - 通知対象外(卒業 / 退会)のメンバーには送らない
 */
class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: ChatRoom, 1: User, 2: User, 3: User}
     */
    private function createRoom(): array
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->inProgress()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $coach2 = User::factory()->coach()->inProgress()->create();
        $certification = Certification::factory()->published()->create();
        foreach ([$coach, $coach2] as $c) {
            $certification->coaches()->attach($c->id, [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $enrollment = Enrollment::factory()->for($student)->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        foreach ([$student, $coach, $coach2] as $member) {
            ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $member->id]);
        }

        return [$room, $student, $coach, $coach2];
    }

    public function test_student_message_notifies_all_coaches_except_sender(): void
    {
        Notification::fake();
        [$room, $student, $coach, $coach2] = $this->createRoom();

        $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), ['body' => '質問があります'])
            ->assertRedirect(route('chat.show', $room));

        foreach ([$coach, $coach2] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                ChatMessageReceivedNotification::class,
                fn (ChatMessageReceivedNotification $n, array $channels) => $channels === ['database', 'mail']
                    && $n->toArray($recipient)['url'] === route('chat.show', $room, false)
            );
        }
        Notification::assertNotSentTo($student, ChatMessageReceivedNotification::class);
    }

    public function test_coach_message_notifies_student_and_other_coach(): void
    {
        Notification::fake();
        [$room, $student, $coach, $coach2] = $this->createRoom();

        $this->actingAs($coach)
            ->post(route('chat.storeMessage', $room), ['body' => '回答します'])
            ->assertRedirect();

        Notification::assertSentTo($student, ChatMessageReceivedNotification::class);
        Notification::assertSentTo($coach2, ChatMessageReceivedNotification::class);
        Notification::assertNotSentTo($coach, ChatMessageReceivedNotification::class);
    }

    public function test_withdrawn_member_is_not_notified(): void
    {
        Notification::fake();
        [$room, $student, $coach, $coach2] = $this->createRoom();
        $coach2->update(['status' => UserStatus::Withdrawn]);

        $this->actingAs($student)
            ->post(route('chat.storeMessage', $room), ['body' => '質問があります'])
            ->assertRedirect();

        Notification::assertSentTo($coach, ChatMessageReceivedNotification::class);
        Notification::assertNotSentTo($coach2, ChatMessageReceivedNotification::class);
    }
}

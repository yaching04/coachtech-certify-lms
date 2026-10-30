<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingStatus;
use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\ChatMessageReceivedNotification;
use App\Notifications\MeetingCanceledNotification;
use App\Notifications\MeetingReservedNotification;
use App\Notifications\QaReplyReceivedNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * 開発用 アプリ内通知シーダー。
 *
 * 固定 student(student@certify-lms.test)と固定 coach(coach@certify-lms.test)に、既存の Seeder が作った
 * 質問回答 / チャット / 面談のレコードを元にした通知を {@see self::PER_USER} 件ずつ投入する。
 * 一覧が 2 ページ目まで続き(20 件/ページ)、未読・既読と通知種別が混在する状態を確認できる。
 * メールは送らず、notifications テーブルへ直接 INSERT する(data は各 Notification の toArray と同一)。
 *
 * 依存順序: `MentoringSeeder` / `ChatSeeder` / `QaThreadSeeder` → 本 Seeder。
 */
final class NotificationSeeder extends Seeder
{
    private const PER_USER = 25;

    public function run(): void
    {
        $student = User::query()->where('email', 'student@certify-lms.test')->first();
        $coach = User::query()->where('email', 'coach@certify-lms.test')->first();

        if ($student !== null) {
            $this->seedFor($student, $this->studentNotifications($student));
        }

        if ($coach !== null) {
            $this->seedFor($coach, $this->coachNotifications($coach));
        }
    }

    /**
     * @return Collection<int, AppNotification>
     */
    private function studentNotifications(User $student): Collection
    {
        $replies = QaReply::query()
            ->whereHas('thread', fn ($q) => $q->where('user_id', $student->id))
            ->where('user_id', '!=', $student->id)
            ->with(['thread', 'user'])
            ->get()
            ->map(fn (QaReply $reply) => new QaReplyReceivedNotification($reply));

        $canceled = Meeting::query()
            ->where('student_id', $student->id)
            ->where('status', MeetingStatus::Canceled->value)
            ->where('canceled_by_user_id', '!=', $student->id)
            ->with(['student', 'canceledBy'])
            ->get()
            ->map(fn (Meeting $meeting) => new MeetingCanceledNotification($meeting));

        return $this->interleave($this->chatNotifications($student), $replies, $canceled);
    }

    /**
     * @return Collection<int, AppNotification>
     */
    private function coachNotifications(User $coach): Collection
    {
        $reserved = Meeting::query()
            ->where('coach_id', $coach->id)
            ->where('status', MeetingStatus::Reserved->value)
            ->with('student')
            ->get()
            ->map(fn (Meeting $meeting) => new MeetingReservedNotification($meeting));

        $canceled = Meeting::query()
            ->where('coach_id', $coach->id)
            ->where('status', MeetingStatus::Canceled->value)
            ->where('canceled_by_user_id', '!=', $coach->id)
            ->with(['student', 'canceledBy'])
            ->get()
            ->map(fn (Meeting $meeting) => new MeetingCanceledNotification($meeting));

        return $this->interleave($this->chatNotifications($coach), $reserved, $canceled);
    }

    /**
     * @return Collection<int, AppNotification>
     */
    private function chatNotifications(User $user): Collection
    {
        return ChatMessage::query()
            ->whereIn('chat_room_id', ChatMember::query()->where('user_id', $user->id)->select('chat_room_id'))
            ->where('sender_user_id', '!=', $user->id)
            ->with('sender')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ChatMessage $message) => new ChatMessageReceivedNotification($message));
    }

    /**
     * 種別が偏らないよう、複数の通知リストから 1 件ずつ交互に取り出して並べる。
     *
     * @param Collection<int, AppNotification> ...$lists
     *
     * @return Collection<int, AppNotification>
     */
    private function interleave(Collection ...$lists): Collection
    {
        $result = collect();
        $max = max(array_map(fn (Collection $list) => $list->count(), $lists));

        for ($i = 0; $i < $max; $i++) {
            foreach ($lists as $list) {
                if ($list->has($i)) {
                    $result->push($list->get($i));
                }
            }
        }

        return $result;
    }

    /**
     * 新しい順に PER_USER 件を投入する。元データが足りない場合は繰り返し使い、3 件に 1 件を未読にする。
     *
     * @param Collection<int, AppNotification> $notifications
     */
    private function seedFor(User $user, Collection $notifications): void
    {
        if ($notifications->isEmpty()) {
            return;
        }

        for ($i = 0; $i < self::PER_USER; $i++) {
            /** @var AppNotification $notification */
            $notification = $notifications->get($i % $notifications->count());
            $createdAt = now()->subHours($i * 7);

            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($user),
                'read_at' => $i % 3 === 0 ? null : $createdAt->copy()->addHour(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}

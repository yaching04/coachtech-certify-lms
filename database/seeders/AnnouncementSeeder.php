<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * 開発用 運営お知らせシーダー。
 *
 * 配信対象 3 種類(全受講生 / 資格指定 / ユーザー指定)のお知らせを 1 件ずつ作り、配信対象の受講中受講生へ
 * 通知(admin_announcement)を直接 INSERT する(メールは送らない)。固定 student(student@certify-lms.test)は
 * 3 件とも受信するため、受講生側の通知一覧 → 通知詳細で本文全文を確認できる。
 *
 * 依存順序: `UserSeeder` / `EnrollmentSeeder` → 本 Seeder。
 */
final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@certify-lms.test')->first();
        $student = User::query()->where('email', 'student@certify-lms.test')->first();

        if ($admin === null || $student === null) {
            return;
        }

        $this->dispatch(
            $admin,
            [
                'title' => '【メンテナンス予告】システムメンテナンスのお知らせ',
                'body' => "いつも Certify LMS をご利用いただきありがとうございます。\n\n下記の日程でシステムメンテナンスを実施します。\nメンテナンス中は学習画面・模試・チャットをご利用いただけません。\n\n日時: 来週土曜日 2:00〜5:00\n\nご不便をおかけしますが、ご理解のほどよろしくお願いいたします。",
                'target_type' => AnnouncementTargetType::AllStudents,
            ],
            $this->students(),
            now()->subDays(3)->setTime(10, 0),
        );

        $enrollment = Enrollment::query()->where('user_id', $student->id)->with('certification')->first();
        if ($enrollment !== null) {
            $this->dispatch(
                $admin,
                [
                    'title' => '【'.$enrollment->certification->name.'】教材を更新しました',
                    'body' => "{$enrollment->certification->name} の教材を最新の試験範囲に合わせて更新しました。\n学習中のセクションの内容が一部変わっている場合があります。\n\n不明点はチャットで担当コーチへご相談ください。",
                    'target_type' => AnnouncementTargetType::Certification,
                    'target_certification_id' => $enrollment->certification_id,
                ],
                $this->students()->whereHas('enrollments', fn ($q) => $q->where('certification_id', $enrollment->certification_id)),
                now()->subDays(2)->setTime(15, 30),
            );
        }

        $this->dispatch(
            $admin,
            [
                'title' => '学習状況のフォローのご連絡',
                'body' => "{$student->name} さん\n\n最近の学習お疲れさまです。目標試験日に向けて学習ペースの相談があれば、面談予約からお気軽にご相談ください。",
                'target_type' => AnnouncementTargetType::User,
                'target_user_id' => $student->id,
            ],
            $this->students()->whereKey($student->id),
            now()->subDay()->setTime(9, 0),
        );
    }

    /**
     * @return Builder<User>
     */
    private function students(): Builder
    {
        return User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value);
    }

    /**
     * @param array{title: string, body: string, target_type: AnnouncementTargetType, target_certification_id?: string, target_user_id?: string} $attributes
     * @param Builder<User> $recipients
     */
    private function dispatch(User $admin, array $attributes, Builder $recipients, CarbonInterface $dispatchedAt): void
    {
        $users = $recipients->get();

        $announcement = Announcement::create([
            'title' => $attributes['title'],
            'body' => $attributes['body'],
            'target_type' => $attributes['target_type']->value,
            'target_certification_id' => $attributes['target_certification_id'] ?? null,
            'target_user_id' => $attributes['target_user_id'] ?? null,
            'dispatched_count' => $users->count(),
            'dispatched_at' => $dispatchedAt,
            'created_by_user_id' => $admin->id,
            'created_at' => $dispatchedAt,
            'updated_at' => $dispatchedAt,
        ]);

        $notification = new AdminAnnouncementNotification($announcement);

        foreach ($users as $user) {
            $user->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => AdminAnnouncementNotification::class,
                'data' => $notification->toArray($user),
                'read_at' => null,
                'created_at' => $dispatchedAt,
                'updated_at' => $dispatchedAt,
            ]);
        }
    }
}

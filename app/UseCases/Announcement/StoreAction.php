<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\Announcement\AnnouncementNoRecipientsException;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use App\Services\NotificationDispatchService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 運営お知らせを即時配信し、配信履歴を記録するユースケース。
 *
 * 1. 配信対象タイプから受信者(受講中の受講生のみ)を解決する。0 名なら配信せず 422 で差し戻す
 * 2. 配信履歴(announcements)を作成し、既存の通知基盤でアプリ内通知 + メールを同期送信する
 * 3. 実際に送信した人数を dispatched_count に記録する
 *
 * 配信は不可逆のため、配信履歴の更新・削除・再配信の経路は持たない。送信途中で例外が起きた場合は
 * トランザクションごとロールバックし(配信履歴・アプリ内通知は残らない)、管理者の再操作に委ねる。
 */
final class StoreAction
{
    public function __construct(private readonly NotificationDispatchService $notifier) {}

    /**
     * @param array{title: string, body: string, target_type: string, target_certification_id?: ?string, target_user_id?: ?string} $validated
     */
    public function __invoke(User $admin, array $validated): Announcement
    {
        $targetType = AnnouncementTargetType::from($validated['target_type']);
        $certificationId = $targetType === AnnouncementTargetType::Certification ? $validated['target_certification_id'] : null;
        $userId = $targetType === AnnouncementTargetType::User ? $validated['target_user_id'] : null;

        return DB::transaction(function () use ($admin, $validated, $targetType, $certificationId, $userId) {
            $recipients = $this->recipients($targetType, $certificationId, $userId);

            if ($recipients->isEmpty()) {
                throw new AnnouncementNoRecipientsException;
            }

            $announcement = Announcement::create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $targetType->value,
                'target_certification_id' => $certificationId,
                'target_user_id' => $userId,
                'dispatched_count' => 0,
                'dispatched_at' => now(),
                'created_by_user_id' => $admin->id,
            ]);

            $count = $this->notifier->send($recipients, new AdminAnnouncementNotification($announcement));
            $announcement->update(['dispatched_count' => $count]);

            return $announcement;
        });
    }

    /**
     * 配信対象の受講生。どのタイプでも受講中(in_progress)の受講生だけに絞る(コーチ・管理者・卒業・退会は対象外)。
     *
     * @return Collection<int, User>
     */
    private function recipients(AnnouncementTargetType $targetType, ?string $certificationId, ?string $userId): Collection
    {
        $query = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->orderBy('id');

        match ($targetType) {
            AnnouncementTargetType::AllStudents => null,
            AnnouncementTargetType::Certification => $query->whereHas(
                'enrollments',
                fn ($q) => $q->where('certification_id', $certificationId),
            ),
            AnnouncementTargetType::User => $query->whereKey($userId),
        };

        return $query->get();
    }
}

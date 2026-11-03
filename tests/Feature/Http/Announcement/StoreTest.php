<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `POST /admin/announcements`(運営お知らせの配信)の検証。
 *
 * - 全受講生 / 資格指定 / ユーザー指定の 3 タイプで、受講中の受講生だけに届く
 * - 資格指定は、指定資格を「学習中」で受講登録している受講生だけに届く(修了・学習中止・別資格のみは対象外)
 * - コーチ・管理者・招待中・卒業・退会には届かない
 * - アプリ内通知とメールの両方で届き、配信件数・配信日時・配信者が記録される
 * - 対象 0 名(資格指定で修了・学習中止しかいない場合を含む)は配信しない / 入力バリデーション / admin 以外は 403
 */
class StoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'メンテナンスのお知らせ',
            'body' => "来週メンテナンスを行います。\nご注意ください。",
            'target_type' => 'all_students',
        ], $overrides);
    }

    public function test_all_students_reaches_only_in_progress_students(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $students = User::factory()->student()->inProgress()->count(2)->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $graduated = User::factory()->student()->graduated()->create();
        $withdrawn = User::factory()->student()->withdrawn()->create();
        $invited = User::factory()->student()->invited()->create();

        $response = $this->actingAs($admin)->post(route('admin.announcements.store'), $this->payload());

        $announcement = Announcement::sole();
        $response->assertRedirect(route('admin.announcements.show', $announcement))
            ->assertSessionHas('success');
        $this->assertSame(2, $announcement->dispatched_count);
        $this->assertNotNull($announcement->dispatched_at);
        $this->assertSame($admin->id, $announcement->created_by_user_id);

        foreach ($students as $student) {
            Notification::assertSentTo(
                $student,
                AdminAnnouncementNotification::class,
                function (AdminAnnouncementNotification $n, array $channels) use ($student) {
                    $data = $n->toArray($student);

                    return $channels === ['database', 'mail']
                        && $data['notification_type'] === 'admin_announcement'
                        && $data['title'] === 'メンテナンスのお知らせ'
                        && $data['body'] === "来週メンテナンスを行います。\nご注意ください。"
                        && $data['url'] === null;
                }
            );
        }
        foreach ([$admin, $coach, $graduated, $withdrawn, $invited] as $excluded) {
            Notification::assertNotSentTo($excluded, AdminAnnouncementNotification::class);
        }
    }

    public function test_certification_target_reaches_only_learning_enrolled_in_progress_students(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $learning = User::factory()->student()->inProgress()->create();
        $passed = User::factory()->student()->inProgress()->create();
        $failed = User::factory()->student()->inProgress()->create();
        $enrolledGraduated = User::factory()->student()->graduated()->create();
        $notEnrolled = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($learning)->for($certification)->learning()->create();
        Enrollment::factory()->for($passed)->for($certification)->passed()->create();
        Enrollment::factory()->for($failed)->for($certification)->failed()->create();
        Enrollment::factory()->for($enrolledGraduated)->for($certification)->learning()->create();
        Enrollment::factory()->for($notEnrolled)->for($otherCertification)->learning()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), $this->payload([
            'target_type' => 'certification',
            'target_certification_id' => $certification->id,
            'target_user_id' => $notEnrolled->id, // 選んだタイプに対応しない欄は無視される
        ]))->assertRedirect();

        $announcement = Announcement::sole();
        $this->assertSame($certification->id, $announcement->target_certification_id);
        $this->assertNull($announcement->target_user_id);
        $this->assertSame(1, $announcement->dispatched_count);
        Notification::assertSentTo($learning, AdminAnnouncementNotification::class);
        // 修了・学習中止は、ユーザー全体が受講中でも対象外(PM 確認済み)
        Notification::assertNotSentTo($passed, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($failed, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($enrolledGraduated, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($notEnrolled, AdminAnnouncementNotification::class);
    }

    public function test_certification_target_with_learning_in_other_cert_and_passed_in_target_is_excluded(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $otherCertification = Certification::factory()->published()->create();
        $learning = User::factory()->student()->inProgress()->create();
        // 指定資格は修了済み・別資格を学習中の受講生(別資格の「学習中」で対象に入らないこと)
        $passedHereLearningElsewhere = User::factory()->student()->inProgress()->create();
        Enrollment::factory()->for($learning)->for($certification)->learning()->create();
        Enrollment::factory()->for($passedHereLearningElsewhere)->for($certification)->passed()->create();
        Enrollment::factory()->for($passedHereLearningElsewhere)->for($otherCertification)->learning()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), $this->payload([
            'target_type' => 'certification',
            'target_certification_id' => $certification->id,
        ]))->assertRedirect();

        $this->assertSame(1, Announcement::sole()->dispatched_count);
        Notification::assertSentTo($learning, AdminAnnouncementNotification::class);
        Notification::assertNotSentTo($passedHereLearningElsewhere, AdminAnnouncementNotification::class);
    }

    public function test_user_target_reaches_only_that_student(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->inProgress()->create();
        $other = User::factory()->student()->inProgress()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), $this->payload([
            'target_type' => 'user',
            'target_user_id' => $target->id,
        ]))->assertRedirect();

        $this->assertSame(1, Announcement::sole()->dispatched_count);
        $notification = $target->notifications()->sole();
        $this->assertNull($notification->read_at);
        $this->assertSame('admin_announcement', $notification->data['notification_type']);
        $this->assertSame(0, $other->notifications()->count());
    }

    public function test_user_target_must_be_in_progress_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $graduated = User::factory()->student()->graduated()->create();

        foreach ([$coach, $graduated] as $user) {
            $this->actingAs($admin)
                ->post(route('admin.announcements.store'), $this->payload(['target_type' => 'user', 'target_user_id' => $user->id]))
                ->assertSessionHasErrors('target_user_id');
        }

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_validation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), ['title' => '', 'body' => '', 'target_type' => 'coaches'])
            ->assertSessionHasErrors(['title', 'body', 'target_type']);

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), $this->payload(['title' => str_repeat('あ', 201), 'body' => str_repeat('あ', 5001)]))
            ->assertSessionHasErrors(['title', 'body']);

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), $this->payload(['target_type' => 'certification']))
            ->assertSessionHasErrors('target_certification_id');

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), $this->payload(['target_type' => 'user']))
            ->assertSessionHasErrors('target_user_id');

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_no_recipients_is_rejected_without_history(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        // 指定資格の受講登録が修了・学習中止だけなら、配信対象は 0 名として扱う
        Enrollment::factory()->for(User::factory()->student()->inProgress()->create())->for($certification)->passed()->create();
        Enrollment::factory()->for(User::factory()->student()->inProgress()->create())->for($certification)->failed()->create();

        $this->actingAs($admin)
            ->from(route('admin.announcements.create'))
            ->post(route('admin.announcements.store'), $this->payload([
                'target_type' => 'certification',
                'target_certification_id' => $certification->id,
            ]))
            ->assertRedirect(route('admin.announcements.create'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('announcements', 0);
        Notification::assertNothingSent();
    }

    public function test_non_admin_cannot_dispatch(): void
    {
        User::factory()->student()->inProgress()->create();

        foreach ([User::factory()->coach()->inProgress()->create(), User::factory()->student()->inProgress()->create()] as $user) {
            $this->actingAs($user)
                ->post(route('admin.announcements.store'), $this->payload())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('announcements', 0);
    }
}

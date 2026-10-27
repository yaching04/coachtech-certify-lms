<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 実際のルートに対して、プラン機能ガード(`active-learning`)が効いていることを検証する。
 *
 * - 修了(graduated)などの受講中以外の受講生はプラン機能で 403
 * - 受講中の受講生は従来どおり利用できる
 * - プロフィール / ダッシュボード / 受講登録の閲覧はガード対象外で、修了後も利用できる
 */
class PlanFeatureAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function nonActiveStatuses(): array
    {
        return [
            'graduated' => ['graduated'],
            'invited' => ['invited'],
            'withdrawn' => ['withdrawn'],
        ];
    }

    private function studentWithStatus(string $status): User
    {
        return User::factory()->student()->{$status}()->create();
    }

    /**
     * @return array<string, string> 画面名 => URL
     */
    private function planFeatureUrls(Enrollment $enrollment): array
    {
        return [
            '資格カタログ' => route('certifications.index'),
            '教材閲覧' => route('learning.enrollments.show', $enrollment),
            '模試' => route('mock-exam.catalog.index', $enrollment),
            '面談予約' => route('meetings.create', $enrollment),
            'チャット' => route('chat.index'),
            '質問掲示板' => route('qa-board.index'),
        ];
    }

    /**
     * @dataProvider nonActiveStatuses
     */
    public function test_non_active_student_is_forbidden_on_plan_features(string $status): void
    {
        $student = $this->studentWithStatus($status);
        $enrollment = Enrollment::factory()->learning()->for($student)->create();

        foreach ($this->planFeatureUrls($enrollment) as $label => $url) {
            $this->actingAs($student)->get($url)->assertForbidden($label.' は '.$status.' では 403 になること');
        }
    }

    public function test_in_progress_student_can_use_plan_features(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->learning()->for($student)->create();

        $this->actingAs($student)->get(route('certifications.index'))->assertOk();
        $this->actingAs($student)->get(route('chat.index'))->assertOk();
        $this->actingAs($student)->get(route('qa-board.index'))->assertOk();
        $this->actingAs($student)->get(route('learning.enrollments.show', $enrollment))->assertOk();
    }

    public function test_graduated_student_can_still_open_profile_dashboard_and_enrollments(): void
    {
        $student = User::factory()->student()->graduated()->create();
        $enrollment = Enrollment::factory()->passed()->for($student)->create();

        $this->actingAs($student)->get(route('settings.profile.edit'))->assertOk();
        $this->actingAs($student)->get(route('dashboard.index'))->assertOk();
        $this->actingAs($student)->get(route('enrollments.index'))->assertOk();
        $this->actingAs($student)->get(route('enrollments.show', $enrollment))->assertOk();
    }

    public function test_graduated_student_cannot_post_to_plan_features(): void
    {
        $student = User::factory()->student()->graduated()->create();

        $this->actingAs($student)
            ->post(route('qa-board.store'), ['title' => 'テスト', 'body' => 'テスト'])
            ->assertForbidden();
    }
}

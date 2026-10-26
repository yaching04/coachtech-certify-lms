<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\EnrollmentGoal;

use App\Exceptions\Enrollment\EnrollmentGoalInvalidTransitionException;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\UseCases\Enrollment\IndexAction;
use App\UseCases\Enrollment\ShowAction;
use App\UseCases\EnrollmentGoal\DestroyAction;
use App\UseCases\EnrollmentGoal\MarkAchievedAction;
use App\UseCases\EnrollmentGoal\StoreAction;
use App\UseCases\EnrollmentGoal\UnmarkAchievedAction;
use App\UseCases\EnrollmentGoal\UpdateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 個人目標の各 Action(Store / Update / Destroy / MarkAchieved / UnmarkAchieved)の単体検証。
 */
class ActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_unachieved_goal(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();

        $goal = app(StoreAction::class)($enrollment, ['title' => '目標', 'description' => '詳細', 'target_date' => '2030-01-01']);

        $this->assertTrue($goal->exists);
        $this->assertSame($enrollment->id, $goal->enrollment_id);
        $this->assertFalse($goal->isAchieved());
        $this->assertSame('2030-01-01', $goal->target_date->toDateString());
    }

    public function test_store_defaults_optional_fields_to_null(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();

        $goal = app(StoreAction::class)($enrollment, ['title' => '目標']);

        $this->assertNull($goal->description);
        $this->assertNull($goal->target_date);
    }

    public function test_update_changes_text_fields_and_keeps_achieved_state(): void
    {
        $goal = EnrollmentGoal::factory()->achieved()->create();

        app(UpdateAction::class)($goal, ['title' => '更新後', 'description' => null, 'target_date' => '2031-02-03']);

        $goal->refresh();
        $this->assertSame('更新後', $goal->title);
        $this->assertSame('2031-02-03', $goal->target_date->toDateString());
        $this->assertTrue($goal->isAchieved());
    }

    public function test_destroy_deletes_goal_physically(): void
    {
        $goal = EnrollmentGoal::factory()->create();

        app(DestroyAction::class)($goal);

        $this->assertDatabaseMissing('enrollment_goals', ['id' => $goal->id]);
    }

    public function test_mark_achieved_sets_achieved_at(): void
    {
        $goal = EnrollmentGoal::factory()->create();

        app(MarkAchievedAction::class)($goal);

        $this->assertNotNull($goal->refresh()->achieved_at);
    }

    public function test_mark_achieved_throws_when_already_achieved(): void
    {
        $goal = EnrollmentGoal::factory()->achieved()->create();

        $this->expectException(EnrollmentGoalInvalidTransitionException::class);

        app(MarkAchievedAction::class)($goal);
    }

    public function test_unmark_achieved_clears_achieved_at(): void
    {
        $goal = EnrollmentGoal::factory()->achieved()->create();

        app(UnmarkAchievedAction::class)($goal);

        $this->assertNull($goal->refresh()->achieved_at);
    }

    public function test_unmark_achieved_throws_when_not_achieved(): void
    {
        $goal = EnrollmentGoal::factory()->create();

        $this->expectException(EnrollmentGoalInvalidTransitionException::class);

        app(UnmarkAchievedAction::class)($goal);
    }

    public function test_enrollment_show_action_loads_goals_in_display_order(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();
        $done = EnrollmentGoal::factory()->forEnrollment($enrollment)->achieved()->create();
        $open = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $result = app(ShowAction::class)($enrollment);

        $this->assertTrue($result->relationLoaded('goals'));
        $this->assertSame([$open->id, $done->id], $result->goals->pluck('id')->all());
    }

    public function test_enrollment_index_action_counts_goals(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->learning()->create();
        EnrollmentGoal::factory()->count(3)->forEnrollment($enrollment)->create();

        $result = app(IndexAction::class)($student);

        $this->assertSame(3, $result->first()->goals_count);
    }
}

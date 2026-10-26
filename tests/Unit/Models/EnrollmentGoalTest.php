<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentGoalTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_achieved_reflects_achieved_at(): void
    {
        $this->assertFalse(EnrollmentGoal::factory()->create()->isAchieved());
        $this->assertTrue(EnrollmentGoal::factory()->achieved()->create()->isAchieved());
    }

    public function test_belongs_to_enrollment_and_enrollment_has_many_goals(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();
        $goal = EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $this->assertTrue($goal->enrollment->is($enrollment));
        $this->assertTrue($enrollment->goals->first()->is($goal));
    }

    public function test_casts_target_date_and_achieved_at(): void
    {
        $goal = EnrollmentGoal::factory()->achieved()->create(['target_date' => '2030-05-06']);
        $goal->refresh();

        $this->assertSame('2030-05-06', $goal->target_date->toDateString());
        $this->assertNotNull($goal->achieved_at);
    }

    public function test_display_order_puts_unachieved_first_then_nearest_date_then_no_date_last(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();
        $factory = EnrollmentGoal::factory()->forEnrollment($enrollment);

        $achievedEarly = $factory->achieved()->create(['title' => '達成・期日近', 'target_date' => now()->addDay()->toDateString()]);
        $noDate = $factory->withoutTargetDate()->create(['title' => '未達成・期日なし']);
        $far = $factory->create(['title' => '未達成・期日遠', 'target_date' => now()->addDays(30)->toDateString()]);
        $near = $factory->create(['title' => '未達成・期日近', 'target_date' => now()->addDays(3)->toDateString()]);

        $ordered = EnrollmentGoal::query()->displayOrder()->pluck('id')->all();

        $this->assertSame([$near->id, $far->id, $noDate->id, $achievedEarly->id], $ordered);
    }

    public function test_soft_deleting_enrollment_removes_goals_but_keeps_other_enrollments_goals(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();
        $other = Enrollment::factory()->learning()->create();
        EnrollmentGoal::factory()->count(2)->forEnrollment($enrollment)->create();
        $kept = EnrollmentGoal::factory()->forEnrollment($other)->create();

        $enrollment->delete();

        $this->assertSame([$kept->id], EnrollmentGoal::query()->pluck('id')->all());
    }

    public function test_force_deleting_enrollment_cascades_goals_via_foreign_key(): void
    {
        $enrollment = Enrollment::factory()->learning()->create();
        EnrollmentGoal::factory()->forEnrollment($enrollment)->create();

        $enrollment->forceDelete();

        $this->assertDatabaseCount('enrollment_goals', 0);
    }
}

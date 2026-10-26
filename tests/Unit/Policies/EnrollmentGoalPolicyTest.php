<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use App\Policies\EnrollmentGoalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * EnrollmentGoalPolicy の検証。追加 / 編集 / 削除 / 達成マーク / 達成解除はすべて受講登録の持ち主である受講生本人のみ。
 */
class EnrollmentGoalPolicyTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentGoalPolicy $policy;

    private User $owner;

    private Enrollment $enrollment;

    private EnrollmentGoal $goal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new EnrollmentGoalPolicy;
        $this->owner = User::factory()->student()->inProgress()->create();
        $this->enrollment = Enrollment::factory()
            ->for($this->owner)
            ->for(Certification::factory()->published())
            ->learning()
            ->create();
        $this->goal = EnrollmentGoal::factory()->forEnrollment($this->enrollment)->create();
    }

    /**
     * @return array<string, bool>
     */
    private function evaluate(User $user): array
    {
        return [
            'create' => $this->policy->create($user, $this->enrollment),
            'update' => $this->policy->update($user, $this->goal),
            'delete' => $this->policy->delete($user, $this->goal),
            'markAchieved' => $this->policy->markAchieved($user, $this->goal),
            'unmarkAchieved' => $this->policy->unmarkAchieved($user, $this->goal),
        ];
    }

    public function test_owner_student_is_allowed_everything(): void
    {
        $this->assertSame(
            ['create' => true, 'update' => true, 'delete' => true, 'markAchieved' => true, 'unmarkAchieved' => true],
            $this->evaluate($this->owner),
        );
    }

    public function test_other_student_is_denied_everything(): void
    {
        $other = User::factory()->student()->inProgress()->create();

        $this->assertSame(
            ['create' => false, 'update' => false, 'delete' => false, 'markAchieved' => false, 'unmarkAchieved' => false],
            $this->evaluate($other),
        );
    }

    public function test_admin_is_denied_everything(): void
    {
        $this->assertSame(
            ['create' => false, 'update' => false, 'delete' => false, 'markAchieved' => false, 'unmarkAchieved' => false],
            $this->evaluate(User::factory()->admin()->create()),
        );
    }

    public function test_assigned_coach_is_denied_everything(): void
    {
        $coach = User::factory()->coach()->create();
        $this->enrollment->certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
        ]);

        $this->assertSame(
            ['create' => false, 'update' => false, 'delete' => false, 'markAchieved' => false, 'unmarkAchieved' => false],
            $this->evaluate($coach),
        );
    }

    public function test_owner_is_denied_to_create_for_cancelled_enrollment(): void
    {
        $this->enrollment->delete();

        $this->assertFalse($this->policy->create($this->owner, $this->enrollment));
    }
}

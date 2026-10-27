<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Enrollment;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * B-B-15: コーチの受講登録管理一覧は、担当している資格の受講生だけを表示する。
 */
class CoachScopeTest extends TestCase
{
    use RefreshDatabase;

    private function assign(Certification $certification, User $coach, bool $active = true): void
    {
        CertificationCoachAssignment::create([
            'id' => (string) Str::ulid(),
            'certification_id' => $certification->id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now()->subDay(),
            'unassigned_at' => $active ? null : now(),
        ]);
    }

    private function enroll(string $studentName, Certification $certification): Enrollment
    {
        $student = User::factory()->student()->create(['name' => $studentName]);

        return Enrollment::factory()->learning()->create([
            'user_id' => $student->id,
            'certification_id' => $certification->id,
        ]);
    }

    public function test_coach_list_shows_only_students_of_assigned_certifications(): void
    {
        $coach = User::factory()->coach()->create();
        $assigned = Certification::factory()->published()->create();
        $other = Certification::factory()->published()->create();
        $this->assign($assigned, $coach);
        $this->enroll('担当内の受講生', $assigned);
        $this->enroll('担当外の受講生', $other);

        $this->actingAs($coach)->get(route('enrollments.index'))
            ->assertOk()
            ->assertSee('担当内の受講生')
            ->assertDontSee('担当外の受講生');
    }

    public function test_coach_list_excludes_certification_whose_assignment_was_released(): void
    {
        $coach = User::factory()->coach()->create();
        $released = Certification::factory()->published()->create();
        $this->assign($released, $coach, active: false);
        $this->enroll('解除済み資格の受講生', $released);

        $this->actingAs($coach)->get(route('enrollments.index'))
            ->assertOk()
            ->assertDontSee('解除済み資格の受講生');
    }

    public function test_coach_without_assignments_sees_no_students(): void
    {
        $coach = User::factory()->coach()->create();
        $this->enroll('誰かの受講生', Certification::factory()->published()->create());

        $this->actingAs($coach)->get(route('enrollments.index'))
            ->assertOk()
            ->assertDontSee('誰かの受講生');
    }

    public function test_certification_filter_cannot_expose_unassigned_certification_students(): void
    {
        $coach = User::factory()->coach()->create();
        $other = Certification::factory()->published()->create();
        $this->assign(Certification::factory()->published()->create(), $coach);
        $this->enroll('担当外の受講生', $other);

        $this->actingAs($coach)->get(route('enrollments.index', ['certification_id' => $other->id]))
            ->assertDontSee('担当外の受講生');
    }

    public function test_admin_still_sees_students_of_all_certifications(): void
    {
        $admin = User::factory()->admin()->create();
        $this->enroll('資格Aの受講生', Certification::factory()->published()->create());
        $this->enroll('資格Bの受講生', Certification::factory()->published()->create());

        $this->actingAs($admin)->get(route('enrollments.index'))
            ->assertOk()
            ->assertSee('資格Aの受講生')
            ->assertSee('資格Bの受講生');
    }
}

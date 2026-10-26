<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use App\Policies\EnrollmentNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * EnrollmentNotePolicy の検証。受講生は全拒否 / 担当コーチは閲覧・追加と自分のメモの更新・削除 / 管理者は全メモ操作可 / 親の受講登録が削除済みなら全拒否。
 */
class EnrollmentNotePolicyTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentNotePolicy $policy;

    private User $admin;

    private User $student;

    private User $coach;

    private User $otherCoach;

    private User $outsideCoach;

    private Enrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new EnrollmentNotePolicy;
        $this->admin = User::factory()->admin()->create();
        $this->student = User::factory()->student()->inProgress()->create();
        $this->coach = User::factory()->coach()->create();
        $this->otherCoach = User::factory()->coach()->create();
        $this->outsideCoach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        foreach ([$this->coach, $this->otherCoach] as $coach) {
            $certification->coaches()->attach($coach->id, [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $this->admin->id,
                'assigned_at' => now(),
            ]);
        }
        $this->enrollment = Enrollment::factory()->for($this->student)->for($certification)->learning()->create();
    }

    private function noteBy(User $author): EnrollmentNote
    {
        return EnrollmentNote::factory()->forEnrollment($this->enrollment)->byAuthor($author)->create();
    }

    public function test_view_any_and_create_allow_admin_and_assigned_coaches_only(): void
    {
        foreach (['viewAny', 'create'] as $ability) {
            $this->assertTrue($this->policy->{$ability}($this->admin, $this->enrollment), "admin {$ability}");
            $this->assertTrue($this->policy->{$ability}($this->coach, $this->enrollment), "coach {$ability}");
            $this->assertTrue($this->policy->{$ability}($this->otherCoach, $this->enrollment), "other coach {$ability}");
            $this->assertFalse($this->policy->{$ability}($this->outsideCoach, $this->enrollment), "outside coach {$ability}");
            $this->assertFalse($this->policy->{$ability}($this->student, $this->enrollment), "student {$ability}");
        }
    }

    public function test_update_and_delete_for_author_coach(): void
    {
        $note = $this->noteBy($this->coach);

        foreach (['update', 'delete'] as $ability) {
            $this->assertTrue($this->policy->{$ability}($this->coach, $note));
            $this->assertFalse($this->policy->{$ability}($this->otherCoach, $note));
            $this->assertFalse($this->policy->{$ability}($this->outsideCoach, $note));
            $this->assertFalse($this->policy->{$ability}($this->student, $note));
        }
    }

    public function test_admin_can_update_and_delete_any_note(): void
    {
        foreach ([$this->coach, $this->otherCoach, $this->admin] as $author) {
            $note = $this->noteBy($author);

            $this->assertTrue($this->policy->update($this->admin, $note));
            $this->assertTrue($this->policy->delete($this->admin, $note));
        }
    }

    public function test_note_without_author_is_only_manageable_by_admin(): void
    {
        $note = $this->noteBy($this->coach);
        $note->forceFill(['author_id' => null])->save();

        $this->assertTrue($this->policy->update($this->admin, $note));
        $this->assertFalse($this->policy->update($this->coach, $note));
    }

    public function test_everything_is_denied_when_enrollment_is_soft_deleted(): void
    {
        $note = $this->noteBy($this->coach);
        $this->enrollment->delete();
        $enrollment = Enrollment::withTrashed()->findOrFail($this->enrollment->id);
        $note = $note->fresh();

        foreach ([$this->admin, $this->coach] as $user) {
            $this->assertFalse($this->policy->viewAny($user, $enrollment));
            $this->assertFalse($this->policy->create($user, $enrollment));
            $this->assertFalse($this->policy->update($user, $note));
            $this->assertFalse($this->policy->delete($user, $note));
        }
    }
}

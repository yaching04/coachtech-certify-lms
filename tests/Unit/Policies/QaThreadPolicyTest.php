<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_any_allows_all_three_roles(): void
    {
        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->viewAny(User::factory()->admin()->create()));
        $this->assertTrue($policy->viewAny(User::factory()->coach()->create()));
        $this->assertTrue($policy->viewAny(User::factory()->student()->create()));
    }

    public function test_view_by_role_and_certification_status(): void
    {
        $policy = new QaThreadPolicy;
        $admin = User::factory()->admin()->create();
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $assigned = Certification::factory()->published()->create();
        $assigned->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $assignedThread = QaThread::factory()->forCertification($assigned)->create();
        $otherThread = QaThread::factory()->create();
        $draftThread = QaThread::factory()->forCertification(Certification::factory()->draft()->create())->create();

        $this->assertTrue($policy->view($admin, $draftThread));
        $this->assertTrue($policy->view($student, $otherThread));
        $this->assertFalse($policy->view($student, $draftThread));
        $this->assertTrue($policy->view($coach, $assignedThread));
        $this->assertFalse($policy->view($coach, $otherThread));
    }

    public function test_create_is_student_only(): void
    {
        $policy = new QaThreadPolicy;

        $this->assertTrue($policy->create(User::factory()->student()->create()));
        $this->assertFalse($policy->create(User::factory()->coach()->create()));
        $this->assertFalse($policy->create(User::factory()->admin()->create()));
    }

    public function test_update_and_resolve_are_author_only(): void
    {
        $policy = new QaThreadPolicy;
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($author)->create();
        $other = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();

        foreach (['update', 'resolve', 'unresolve'] as $ability) {
            $this->assertTrue($policy->{$ability}($author, $thread), $ability);
            $this->assertFalse($policy->{$ability}($other, $thread), $ability);
            $this->assertFalse($policy->{$ability}($admin, $thread), $ability);
        }
    }

    public function test_author_loses_update_when_certification_unpublished(): void
    {
        $policy = new QaThreadPolicy;
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($author)->forCertification(Certification::factory()->archived()->create())->create();

        $this->assertFalse($policy->update($author, $thread));
        $this->assertFalse($policy->delete($author, $thread));
    }

    public function test_delete_allows_admin_and_author(): void
    {
        $policy = new QaThreadPolicy;
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($author)->create();

        $this->assertTrue($policy->delete(User::factory()->admin()->create(), $thread));
        $this->assertTrue($policy->delete($author, $thread));
        $this->assertFalse($policy->delete(User::factory()->student()->create(), $thread));
        $this->assertFalse($policy->delete(User::factory()->coach()->create(), $thread));
    }
}

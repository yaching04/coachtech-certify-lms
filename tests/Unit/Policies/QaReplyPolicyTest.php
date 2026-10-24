<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaReplyPolicy;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaReplyPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function policy(): QaReplyPolicy
    {
        return new QaReplyPolicy(new QaThreadPolicy);
    }

    public function test_create_allows_student_and_assigned_coach_but_not_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $cert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $thread = QaThread::factory()->forCertification($cert)->create();
        $otherThread = QaThread::factory()->create();

        $this->assertTrue($this->policy()->create(User::factory()->student()->create(), $thread));
        $this->assertTrue($this->policy()->create($coach, $thread));
        $this->assertFalse($this->policy()->create($coach, $otherThread));
        $this->assertFalse($this->policy()->create($admin, $thread));
    }

    public function test_create_denied_on_unpublished_certification(): void
    {
        $thread = QaThread::factory()->forCertification(Certification::factory()->draft()->create())->create();

        $this->assertFalse($this->policy()->create(User::factory()->student()->create(), $thread));
    }

    public function test_update_is_author_only_and_admin_cannot_edit(): void
    {
        $author = User::factory()->coach()->create();
        $reply = QaReply::factory()->byUser($author)->create();

        $this->assertTrue($this->policy()->update($author, $reply));
        $this->assertFalse($this->policy()->update(User::factory()->student()->create(), $reply));
        $this->assertFalse($this->policy()->update(User::factory()->admin()->create(), $reply));
    }

    public function test_delete_allows_author_and_admin(): void
    {
        $author = User::factory()->student()->create();
        $reply = QaReply::factory()->byUser($author)->create();

        $this->assertTrue($this->policy()->delete($author, $reply));
        $this->assertTrue($this->policy()->delete(User::factory()->admin()->create(), $reply));
        $this->assertFalse($this->policy()->delete(User::factory()->student()->create(), $reply));
    }
}

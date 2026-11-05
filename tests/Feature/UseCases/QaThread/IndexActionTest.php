<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_eager_loads_user_certification_and_reply_count(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->forThread($thread)->count(3)->create();

        $result = app(IndexAction::class)($student, []);

        $first = $result->first();
        $this->assertTrue($first->relationLoaded('user'));
        $this->assertTrue($first->relationLoaded('certification'));
        $this->assertSame(3, $first->replies_count);
        $this->assertSame(IndexAction::PER_PAGE, $result->perPage());
    }

    public function test_ties_on_created_at_are_ordered_by_id_desc(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $at = now()->subDay();
        $threads = QaThread::factory()->count(3)->create(['created_at' => $at]);

        $ids = app(IndexAction::class)($student, [])->pluck('id')->all();

        $this->assertSame($threads->pluck('id')->sortDesc()->values()->all(), $ids);
    }

    public function test_filterable_certifications_by_role(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $student = User::factory()->student()->inProgress()->create();
        $published = Certification::factory()->published()->create();
        $assigned = Certification::factory()->published()->create();
        $assignedDraft = Certification::factory()->draft()->create();
        foreach ([$assigned, $assignedDraft] as $cert) {
            $cert->coaches()->attach($coach->id, [
                'id' => (string) Str::ulid(),
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
            ]);
        }

        $action = app(IndexAction::class);

        $this->assertEqualsCanonicalizing(
            [$published->id, $assigned->id, $assignedDraft->id],
            $action->filterableCertifications($admin)->pluck('id')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$published->id, $assigned->id],
            $action->filterableCertifications($student)->pluck('id')->all(),
        );
        $this->assertSame([$assigned->id], $action->filterableCertifications($coach)->pluck('id')->all());
    }
}

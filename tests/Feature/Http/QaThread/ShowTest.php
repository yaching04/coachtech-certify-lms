<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_thread_with_replies_in_chronological_order(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create(['title' => '詳細表示の質問']);
        $later = QaReply::factory()->forThread($thread)->create(['body' => '二番目の回答', 'created_at' => now()]);
        $earlier = QaReply::factory()->forThread($thread)->create(['body' => '最初の回答', 'created_at' => now()->subHour()]);

        $response = $this->actingAs($student)->get(route('qa-board.show', $thread));

        $response->assertOk()
            ->assertViewIs('qa-thread.show')
            ->assertSeeInOrder(['詳細表示の質問', '最初の回答', '二番目の回答']);
        $this->assertSame([$earlier->id, $later->id], $response->viewData('thread')->replies->pluck('id')->all());
    }

    public function test_student_cannot_view_thread_of_unpublished_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->forCertification(Certification::factory()->archived()->create())->byUser($student)->create();

        $this->actingAs($student)->get(route('qa-board.show', $thread))->assertForbidden();
    }

    public function test_coach_can_view_only_assigned_certification_threads(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $assigned = Certification::factory()->published()->create();
        $assigned->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $mine = QaThread::factory()->forCertification($assigned)->create();
        $others = QaThread::factory()->create();

        $this->actingAs($coach)->get(route('qa-board.show', $mine))->assertOk();
        $this->actingAs($coach)->get(route('qa-board.show', $others))->assertForbidden();
    }

    public function test_unknown_thread_returns_404(): void
    {
        $student = User::factory()->student()->inProgress()->create();

        $this->actingAs($student)->get(route('qa-board.show', ['thread' => (string) Str::ulid()]))->assertNotFound();
    }
}

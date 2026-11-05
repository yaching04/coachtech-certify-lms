<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_resolve_and_unresolve(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->byUser($student)->open()->create();

        $this->actingAs($student)
            ->post(route('qa-board.resolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '解決済にしました。');
        $thread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $thread->status);
        $this->assertNotNull($thread->resolved_at);

        $this->actingAs($student)
            ->post(route('qa-board.unresolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '未解決に戻しました。');
        $thread->refresh();
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }

    public function test_invalid_transition_returns_409(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $open = QaThread::factory()->byUser($student)->open()->create();
        $resolved = QaThread::factory()->byUser($student)->resolved()->create();

        $this->actingAs($student)->postJson(route('qa-board.unresolve', $open))->assertStatus(409);
        $this->actingAs($student)->postJson(route('qa-board.resolve', $resolved))->assertStatus(409);
    }

    public function test_non_author_coach_and_admin_cannot_toggle(): void
    {
        $thread = QaThread::factory()->open()->create();

        $this->actingAs(User::factory()->student()->inProgress()->create())
            ->post(route('qa-board.resolve', $thread))
            ->assertForbidden();
        $this->actingAs(User::factory()->coach()->inProgress()->create())
            ->post(route('qa-board.resolve', $thread))
            ->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('qa-board.resolve', $thread))
            ->assertForbidden();

        $this->assertSame(QaThreadStatus::Open, $thread->fresh()->status);
    }
}

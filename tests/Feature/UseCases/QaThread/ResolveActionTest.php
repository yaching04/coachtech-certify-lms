<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Exceptions\QaBoard\QaThreadInvalidTransitionException;
use App\Models\QaThread;
use App\UseCases\QaThread\ResolveAction;
use App\UseCases\QaThread\UnresolveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_sets_status_and_resolved_at(): void
    {
        $this->freezeSecond();
        $thread = QaThread::factory()->open()->create();

        app(ResolveAction::class)($thread);

        $thread->refresh();
        $this->assertSame(QaThreadStatus::Resolved, $thread->status);
        $this->assertTrue($thread->resolved_at->equalTo(now()));
    }

    public function test_resolve_already_resolved_throws(): void
    {
        $thread = QaThread::factory()->resolved()->create();

        $this->expectException(QaThreadInvalidTransitionException::class);
        $this->expectExceptionMessage('未解決の質問のみ解決済にできます。');

        app(ResolveAction::class)($thread);
    }

    public function test_unresolve_clears_resolved_at(): void
    {
        $thread = QaThread::factory()->resolved()->create();

        app(UnresolveAction::class)($thread);

        $thread->refresh();
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }

    public function test_unresolve_open_thread_throws(): void
    {
        $thread = QaThread::factory()->open()->create();

        $this->expectException(QaThreadInvalidTransitionException::class);
        $this->expectExceptionMessage('解決済の質問のみ未解決に戻せます。');

        app(UnresolveAction::class)($thread);
    }
}

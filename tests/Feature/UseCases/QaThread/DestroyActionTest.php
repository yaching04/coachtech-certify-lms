<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Exceptions\QaBoard\QaThreadNotDeletableException;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_deletes_thread_without_replies(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($student)->create();

        app(DestroyAction::class)($student, $thread);

        $this->assertModelMissing($thread);
    }

    public function test_author_cannot_delete_thread_with_replies(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->byUser($student)->create();
        QaReply::factory()->forThread($thread)->create();

        $this->expectException(QaThreadNotDeletableException::class);

        try {
            app(DestroyAction::class)($student, $thread);
        } finally {
            $this->assertModelExists($thread);
        }
    }

    public function test_admin_deletes_thread_with_replies_cascading(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $replies = QaReply::factory()->forThread($thread)->count(2)->create();

        app(DestroyAction::class)($admin, $thread);

        $this->assertModelMissing($thread);
        $replies->each(fn ($r) => $this->assertModelMissing($r));
    }
}

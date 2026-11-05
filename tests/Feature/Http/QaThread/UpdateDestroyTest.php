<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_can_edit_title_and_body_but_not_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->byUser($student)->create();
        $originalCertId = $thread->certification_id;
        $otherCert = Certification::factory()->published()->create();

        $this->actingAs($student)->get(route('qa-board.edit', $thread))->assertOk()->assertViewIs('qa-thread.edit');

        $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), [
                'title' => '更新後タイトル',
                'body' => '更新後本文',
                'certification_id' => $otherCert->id,
            ])
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '質問を更新しました。');

        $thread->refresh();
        $this->assertSame('更新後タイトル', $thread->title);
        $this->assertSame('更新後本文', $thread->body);
        $this->assertSame($originalCertId, $thread->certification_id);
    }

    public function test_non_author_cannot_edit(): void
    {
        $thread = QaThread::factory()->create(['title' => '元のタイトル']);
        $otherStudent = User::factory()->student()->inProgress()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($otherStudent)->get(route('qa-board.edit', $thread))->assertForbidden();
        $this->actingAs($otherStudent)
            ->patch(route('qa-board.update', $thread), ['title' => 'x', 'body' => 'y'])
            ->assertForbidden();
        $this->actingAs($admin)
            ->patch(route('qa-board.update', $thread), ['title' => 'x', 'body' => 'y'])
            ->assertForbidden();

        $this->assertSame('元のタイトル', $thread->fresh()->title);
    }

    public function test_update_validation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->byUser($student)->create();

        $this->actingAs($student)
            ->patch(route('qa-board.update', $thread), ['title' => '', 'body' => str_repeat('a', 5001)])
            ->assertSessionHasErrors(['title', 'body']);
    }

    public function test_author_can_delete_thread_without_replies(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->byUser($student)->create();

        $this->actingAs($student)
            ->delete(route('qa-board.destroy', $thread))
            ->assertRedirect(route('qa-board.index'))
            ->assertSessionHas('success', '質問を削除しました。');

        $this->assertModelMissing($thread);
    }

    public function test_author_cannot_delete_thread_with_replies(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->byUser($student)->create();
        QaReply::factory()->forThread($thread)->create();

        $this->actingAs($student)
            ->from(route('qa-board.show', $thread))
            ->delete(route('qa-board.destroy', $thread))
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('error', '回答が付いた質問は削除できません。');

        $this->actingAs($student)
            ->deleteJson(route('qa-board.destroy', $thread))
            ->assertStatus(409);

        $this->assertModelExists($thread);
    }

    public function test_non_author_cannot_delete(): void
    {
        $thread = QaThread::factory()->create();

        $this->actingAs(User::factory()->student()->inProgress()->create())
            ->delete(route('qa-board.destroy', $thread))
            ->assertForbidden();

        $this->assertModelExists($thread);
    }
}

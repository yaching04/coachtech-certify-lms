<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_reply_and_is_redirected_to_reply_anchor(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();

        $response = $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => '私はこう解きました。']);

        $reply = QaReply::sole();
        $response->assertRedirect(route('qa-board.show', $thread).'#reply-'.$reply->id)
            ->assertSessionHas('success', '回答を投稿しました。');
        $this->assertSame($student->id, $reply->user_id);
        $this->assertSame($thread->id, $reply->qa_thread_id);
    }

    public function test_assigned_coach_can_reply_but_unassigned_coach_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->inProgress()->create();
        $cert = Certification::factory()->published()->create();
        $cert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $assignedThread = QaThread::factory()->forCertification($cert)->create();
        $otherThread = QaThread::factory()->create();

        $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $assignedThread), ['body' => 'コーチの回答'])
            ->assertRedirect();
        $this->actingAs($coach)
            ->post(route('qa-board.replies.store', $otherThread), ['body' => '担当外'])
            ->assertForbidden();

        $this->assertSame(1, QaReply::count());
    }

    public function test_reply_to_resolved_thread_is_allowed(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->resolved()->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => '補足です'])
            ->assertRedirect();

        $this->assertSame(1, $thread->replies()->count());
    }

    public function test_admin_cannot_reply(): void
    {
        $thread = QaThread::factory()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('qa-board.replies.store', $thread), ['body' => '管理者'])
            ->assertForbidden();

        $this->assertSame(0, QaReply::count());
    }

    public function test_reply_validation(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => ''])
            ->assertSessionHasErrors('body');
        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), ['body' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('body');
    }

    public function test_author_can_edit_and_update_reply(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($student)->create();

        $this->actingAs($student)
            ->get(route('qa-board.replies.edit', [$thread, $reply]))
            ->assertOk()
            ->assertViewIs('qa-thread.reply-edit');

        $this->actingAs($student)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => '修正した回答'])
            ->assertRedirect(route('qa-board.show', $thread).'#reply-'.$reply->id)
            ->assertSessionHas('success', '回答を更新しました。');

        $this->assertSame('修正した回答', $reply->fresh()->body);
    }

    public function test_non_author_cannot_edit_or_update_reply(): void
    {
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->create(['body' => '元の回答']);
        $other = User::factory()->student()->inProgress()->create();

        $this->actingAs($other)->get(route('qa-board.replies.edit', [$thread, $reply]))->assertForbidden();
        $this->actingAs($other)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => '改ざん'])
            ->assertForbidden();

        $this->assertSame('元の回答', $reply->fresh()->body);
    }

    public function test_author_can_delete_reply(): void
    {
        $coach = User::factory()->coach()->inProgress()->create();
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $cert->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $thread = QaThread::factory()->forCertification($cert)->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($coach)->create();

        $this->actingAs($coach)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]))
            ->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '回答を削除しました。');

        $this->assertModelMissing($reply);
    }

    public function test_non_author_cannot_delete_reply(): void
    {
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->create();

        $this->actingAs(User::factory()->student()->inProgress()->create())
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]))
            ->assertForbidden();

        $this->assertModelExists($reply);
    }

    public function test_reply_must_belong_to_thread_in_url(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->byUser($student)->create();

        $this->actingAs($student)->get(route('qa-board.replies.edit', [$thread, $reply]))->assertNotFound();
        $this->actingAs($student)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => 'x'])
            ->assertNotFound();
        $this->actingAs($student)->delete(route('qa-board.replies.destroy', [$thread, $reply]))->assertNotFound();

        $this->assertModelExists($reply);
    }

    public function test_cannot_operate_own_reply_once_certification_unpublished(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->archived()->create();
        $thread = QaThread::factory()->forCertification($cert)->create();
        $reply = QaReply::factory()->forThread($thread)->byUser($student)->create();

        $this->actingAs($student)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => 'x'])
            ->assertForbidden();
        $this->actingAs($student)->delete(route('qa-board.replies.destroy', [$thread, $reply]))->assertForbidden();
    }
}

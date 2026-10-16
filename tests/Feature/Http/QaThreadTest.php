<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaThreadTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $coach;

    private User $admin;

    private Certification $certification;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = User::factory()->create(['role' => UserRole::Student]);
        $this->coach = User::factory()->create(['role' => UserRole::Coach]);
        $this->admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->certification = Certification::factory()->create([
            'status' => CertificationStatus::Published,
        ]);
    }

    public function test_student_can_view_qa_board_index(): void
    {
        QaThread::factory()->create([
            'certification_id' => $this->certification->id,
            'user_id' => $this->student->id,
        ]);

        $response = $this->actingAs($this->student)->get(route('qa-board.index'));

        $response->assertOk();
        $response->assertViewIs('qa-thread.index');
    }

    public function test_student_can_create_thread(): void
    {
        $response = $this->actingAs($this->student)->post(route('qa-board.store'), [
            'certification_id' => $this->certification->id,
            'title' => 'テスト質問',
            'body' => 'これはテストの本文です。',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_threads', [
            'title' => 'テスト質問',
            'user_id' => $this->student->id,
            'status' => QaThreadStatus::Unresolved->value,
        ]);
    }

    public function test_student_can_reply_to_thread(): void
    {
        $thread = QaThread::factory()->create([
            'certification_id' => $this->certification->id,
            'user_id' => $this->student->id,
        ]);

        $response = $this->actingAs($this->student)->post(route('qa-board.replies.store', $thread), [
            'body' => 'テスト回答です。',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $this->student->id,
            'body' => 'テスト回答です。',
        ]);
    }

    public function test_thread_owner_can_resolve(): void
    {
        $thread = QaThread::factory()->create([
            'certification_id' => $this->certification->id,
            'user_id' => $this->student->id,
            'status' => QaThreadStatus::Unresolved,
        ]);

        $response = $this->actingAs($this->student)->post(route('qa-board.resolve', $thread));

        $response->assertRedirect();
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'status' => QaThreadStatus::Resolved->value,
        ]);
    }

    public function test_admin_can_delete_thread(): void
    {
        $thread = QaThread::factory()->create([
            'certification_id' => $this->certification->id,
            'user_id' => $this->student->id,
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.qa-board.destroy', $thread));

        $response->assertRedirect();
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }
}

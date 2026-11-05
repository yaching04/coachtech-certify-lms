<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_open_create_form_with_published_certifications(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $published = Certification::factory()->published()->create();
        $draft = Certification::factory()->draft()->create();

        $response = $this->actingAs($student)->get(route('qa-board.create'));

        $response->assertOk()->assertViewIs('qa-thread.create');
        $ids = $response->viewData('certifications')->pluck('id')->all();
        $this->assertContains($published->id, $ids);
        $this->assertNotContains($draft->id, $ids);
    }

    public function test_student_can_post_thread_for_unenrolled_published_certification(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $cert->id,
            'title' => '計算問題の解き方',
            'body' => 'この問題の解き方を教えてください。',
        ]);

        $thread = QaThread::sole();
        $response->assertRedirect(route('qa-board.show', $thread))
            ->assertSessionHas('success', '質問を投稿しました。');
        $this->assertSame($student->id, $thread->user_id);
        $this->assertSame($cert->id, $thread->certification_id);
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }

    public function test_coach_and_admin_cannot_post_thread(): void
    {
        $cert = Certification::factory()->published()->create();
        $payload = ['certification_id' => $cert->id, 'title' => 't', 'body' => 'b'];

        $this->actingAs(User::factory()->coach()->inProgress()->create())
            ->post(route('qa-board.store'), $payload)
            ->assertForbidden();
        $this->actingAs(User::factory()->coach()->inProgress()->create())
            ->get(route('qa-board.create'))
            ->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('qa-board.store'), $payload)
            ->assertForbidden();

        $this->assertSame(0, QaThread::count());
    }

    public function test_validation_errors(): void
    {
        $student = User::factory()->student()->inProgress()->create();
        $draft = Certification::factory()->draft()->create();

        $this->actingAs($student)
            ->post(route('qa-board.store'), [])
            ->assertSessionHasErrors(['certification_id', 'title', 'body']);

        $this->actingAs($student)
            ->post(route('qa-board.store'), [
                'certification_id' => $draft->id,
                'title' => str_repeat('a', 201),
                'body' => str_repeat('a', 5001),
            ])
            ->assertSessionHasErrors(['certification_id', 'title', 'body']);

        $this->assertSame(0, QaThread::count());
    }
}

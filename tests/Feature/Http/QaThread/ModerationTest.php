<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 管理者モデレーション(/admin/qa-board)の Feature テスト。
 */
class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_threads_of_all_certifications_including_unpublished(): void
    {
        $admin = User::factory()->admin()->create();
        QaThread::factory()->forCertification(Certification::factory()->published()->create())->create(['title' => '公開中の質問']);
        QaThread::factory()->forCertification(Certification::factory()->archived()->create())->create(['title' => '販売終了資格の質問']);
        $draftCert = Certification::factory()->draft()->create();

        $response = $this->actingAs($admin)->get(route('admin.qa-board.index'));

        $response->assertOk()
            ->assertSee('公開中の質問')
            ->assertSee('販売終了資格の質問');
        $this->assertContains($draftCert->id, $response->viewData('certifications')->pluck('id')->all());
        $this->assertSame(2, $response->viewData('threads')->total());
    }

    public function test_admin_can_view_thread_of_unpublished_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->forCertification(Certification::factory()->draft()->create())->create();

        $this->actingAs($admin)->get(route('admin.qa-board.show', $thread))->assertOk();
    }

    public function test_admin_can_delete_thread_with_replies(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.destroy', $thread))
            ->assertRedirect(route('admin.qa-board.index'))
            ->assertSessionHas('success', '質問を削除しました。');

        $this->assertModelMissing($thread);
        $this->assertModelMissing($reply);
    }

    public function test_admin_can_delete_any_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->forThread($thread)->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [$thread, $reply]))
            ->assertRedirect(route('admin.qa-board.show', $thread))
            ->assertSessionHas('success', '回答を削除しました。');

        $this->assertModelMissing($reply);
    }

    public function test_admin_reply_delete_requires_reply_to_belong_to_thread(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $otherReply = QaReply::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [$thread, $otherReply]))
            ->assertNotFound();

        $this->assertModelExists($otherReply);
    }

    public function test_non_admin_cannot_access_moderation_routes(): void
    {
        $thread = QaThread::factory()->create();

        foreach ([User::factory()->student()->inProgress()->create(), User::factory()->coach()->inProgress()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.qa-board.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.qa-board.show', $thread))->assertForbidden();
            $this->actingAs($user)->delete(route('admin.qa-board.destroy', $thread))->assertForbidden();
        }

        $this->assertModelExists($thread);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Models\Announcement;
use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 運営お知らせ 配信履歴の一覧 / 詳細 / 作成フォーム(admin のみ)の検証。
 */
class IndexShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_history_newest_first(): void
    {
        $admin = User::factory()->admin()->create();
        Announcement::factory()->create(['title' => '古いお知らせ', 'dispatched_at' => now()->subDays(2), 'created_by_user_id' => $admin->id]);
        Announcement::factory()->create(['title' => '新しいお知らせ', 'dispatched_at' => now(), 'created_by_user_id' => $admin->id]);

        $this->actingAs($admin)
            ->get(route('admin.announcements.index'))
            ->assertOk()
            ->assertSeeInOrder(['新しいお知らせ', '古いお知らせ']);
    }

    public function test_admin_can_view_detail_and_create_form(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create(['name' => '基本情報技術者']);
        $student = User::factory()->student()->inProgress()->create(['name' => '受講生 太郎']);
        $announcement = Announcement::factory()->forCertification($certification)->create([
            'title' => '教材更新',
            'body' => "1 行目\n2 行目",
            'dispatched_count' => 3,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.announcements.show', $announcement))
            ->assertOk()
            ->assertSee('教材更新')
            ->assertSee('基本情報技術者')
            ->assertSee('資格指定');

        $this->actingAs($admin)
            ->get(route('admin.announcements.create'))
            ->assertOk()
            ->assertSee('基本情報技術者')
            ->assertSee('受講生 太郎');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $announcement = Announcement::factory()->create();

        foreach ([User::factory()->coach()->inProgress()->create(), User::factory()->student()->inProgress()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.announcements.index'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.announcements.create'))->assertForbidden();
            $this->actingAs($user)->get(route('admin.announcements.show', $announcement))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.announcements.index'))->assertRedirect(route('login'));
    }

    public function test_no_edit_update_or_delete_routes(): void
    {
        $admin = User::factory()->admin()->create();
        $announcement = Announcement::factory()->create();
        $url = '/admin/announcements/'.$announcement->id;

        $this->actingAs($admin)->get($url.'/edit')->assertNotFound();
        $this->actingAs($admin)->patch($url, ['title' => 'x'])->assertMethodNotAllowed();
        $this->actingAs($admin)->delete($url)->assertMethodNotAllowed();
        $this->assertDatabaseCount('announcements', 1);
    }
}

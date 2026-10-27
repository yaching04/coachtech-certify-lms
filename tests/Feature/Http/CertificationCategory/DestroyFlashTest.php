<?php

declare(strict_types=1);

namespace Tests\Feature\Http\CertificationCategory;

use App\Models\Certification;
use App\Models\CertificationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B-B-07: 資格分類の削除成功時に、追加・更新と同様に成功フラッシュが表示されることを担保する。
 */
class DestroyFlashTest extends TestCase
{
    use RefreshDatabase;

    public function test_destroy_flashes_success_message_with_fixed_text(): void
    {
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.certification-categories.destroy', $category))
            ->assertRedirect(route('admin.certification-categories.index'))
            ->assertSessionHas('success', '分類を削除しました。');
    }

    public function test_success_message_is_rendered_on_index_after_destroy(): void
    {
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create();

        $this->actingAs($admin)
            ->followingRedirects()
            ->delete(route('admin.certification-categories.destroy', $category))
            ->assertOk()
            ->assertSee('分類を削除しました。');
    }

    public function test_no_success_flash_when_category_is_in_use(): void
    {
        $admin = User::factory()->admin()->create();
        $category = CertificationCategory::factory()->create();
        Certification::factory()->draft()->create(['category_id' => $category->id]);

        $this->actingAs($admin)
            ->deleteJson(route('admin.certification-categories.destroy', $category))
            ->assertStatus(409);

        $this->assertNull(session('success'));
    }
}

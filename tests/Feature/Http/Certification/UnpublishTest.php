<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnpublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_dialog_says_existing_students_can_no_longer_view_materials(): void
    {
        // PM 確認の回答: 既存の受講生も教材を閲覧できなくなるため、「学習継続には影響しません」と案内してはいけない
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($admin)->get(route('admin.certifications.show', $cert))
            ->assertOk()
            ->assertSee('既存の受講生も教材を閲覧できなくなります')
            ->assertDontSee('既存受講生の学習継続には影響しません');
    }

    public function test_admin_unpublishes_published_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.unpublish', $cert));

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $this->assertSame('draft', $cert->fresh()->status->value);
    }

    public function test_cannot_unpublish_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.certifications.unpublish', $cert));

        $response->assertStatus(409);
    }

    public function test_cannot_unpublish_archived(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->archived()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.certifications.unpublish', $cert));

        $response->assertStatus(409);
    }

    public function test_coach_cannot_unpublish(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($coach)->post(route('admin.certifications.unpublish', $cert));

        $response->assertForbidden();
    }
}

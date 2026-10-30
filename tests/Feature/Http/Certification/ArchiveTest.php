<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Certification;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
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

    public function test_admin_archives_published_certification(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $response = $this->actingAs($admin)->post(route('admin.certifications.archive', $cert));

        $response->assertRedirect(route('admin.certifications.show', $cert));
        $this->assertSame('archived', $cert->fresh()->status->value);
        $this->assertNotNull($cert->fresh()->archived_at);
    }

    public function test_cannot_archive_draft(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->draft()->create();

        $response = $this->actingAs($admin)->postJson(route('admin.certifications.archive', $cert));

        $response->assertStatus(409);
    }
}

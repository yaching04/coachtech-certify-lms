<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_draft_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)->delete(route('admin.meeting-packs.destroy', $plan));

        $response->assertRedirect(route('admin.meeting-packs.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('meeting_packs', ['id' => $plan->id]);
    }

    public function test_admin_can_delete_archived_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->archived()->create();

        $response = $this->actingAs($admin)->delete(route('admin.meeting-packs.destroy', $plan));

        $response->assertRedirect(route('admin.meeting-packs.index'));
        $this->assertDatabaseMissing('meeting_packs', ['id' => $plan->id]);
    }

    public function test_cannot_delete_published_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->deleteJson(route('admin.meeting-packs.destroy', $plan));

        $response->assertStatus(409);
        $this->assertDatabaseHas('meeting_packs', ['id' => $plan->id]);
    }

    public function test_published_delete_from_browser_redirects_back_with_error(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)
            ->from(route('admin.meeting-packs.show', $plan))
            ->delete(route('admin.meeting-packs.destroy', $plan));

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $response->assertSessionHas('error', '公開中の面談パックは削除できません。先にアーカイブしてください。');
        $this->assertDatabaseHas('meeting_packs', ['id' => $plan->id]);
    }

    public function test_coach_and_student_cannot_delete(): void
    {
        $plan = MeetingPack::factory()->draft()->create();

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->delete(route('admin.meeting-packs.destroy', $plan))
                ->assertForbidden();
        }

        $this->assertDatabaseHas('meeting_packs', ['id' => $plan->id]);
    }
}

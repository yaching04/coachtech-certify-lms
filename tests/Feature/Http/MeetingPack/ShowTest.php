<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_meeting_pack_detail(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Creator Admin']);
        $plan = MeetingPack::factory()->draft()->create([
            'name' => 'Detail Pack',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.show', $plan));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.show');
        $response->assertSee('Detail Pack');
        $response->assertSee('Creator Admin');
    }

    public function test_draft_shows_publish_and_delete_buttons(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.show', $plan));

        $response->assertSee(route('admin.meeting-packs.publish', $plan));
        $response->assertDontSee(route('admin.meeting-packs.archive', $plan));
        $response->assertDontSee(route('admin.meeting-packs.unarchive', $plan));
        $response->assertSee('onsubmit="return confirm', false);
    }

    public function test_published_hides_delete_button(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.show', $plan));

        $response->assertSee(route('admin.meeting-packs.archive', $plan));
        $response->assertDontSee(route('admin.meeting-packs.publish', $plan));
        $response->assertDontSee('onsubmit="return confirm', false);
    }

    public function test_returns_404_for_unknown_id(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.meeting-packs.show', '01J0000000000000000000ZZZZ'))
            ->assertNotFound();
    }

    public function test_coach_and_student_cannot_view_detail(): void
    {
        $plan = MeetingPack::factory()->published()->create();

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.meeting-packs.show', $plan))
                ->assertForbidden();
        }
    }
}

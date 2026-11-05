<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param array<string, mixed> $override
     *
     * @return array<string, mixed>
     */
    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => '更新後パック',
            'description' => '更新後の説明',
            'meeting_count' => 4,
            'price' => 9800,
            'stripe_price_id' => 'price_updated',
            'sort_order' => 5,
        ], $override);
    }

    public function test_admin_can_view_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.edit', $plan));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.edit');
    }

    public function test_admin_can_update_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->published()->create();

        $response = $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $plan), $this->payload());

        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'name' => '更新後パック',
            'meeting_count' => 4,
            'price' => 9800,
            'stripe_price_id' => 'price_updated',
            'sort_order' => 5,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_status_is_not_changed_by_update(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->draft()->create();

        $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $plan), $this->payload(['status' => 'published']));

        $this->assertSame('draft', $plan->fresh()->status->value);
    }

    public function test_validation_errors_keep_record_unchanged(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create(['name' => '元の名前']);

        $this->actingAs($admin)
            ->patch(route('admin.meeting-packs.update', $plan), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->assertSame('元の名前', $plan->fresh()->name);
    }

    public function test_coach_and_student_cannot_update(): void
    {
        $plan = MeetingPack::factory()->create(['name' => '元の名前']);

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.meeting-packs.edit', $plan))
                ->assertForbidden();

            $this->actingAs($user)
                ->patch(route('admin.meeting-packs.update', $plan), $this->payload())
                ->assertForbidden();
        }

        $this->assertSame('元の名前', $plan->fresh()->name);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
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
            'name' => '7 回パック',
            'description' => '説明文',
            'meeting_count' => 7,
            'price' => 18000,
            'stripe_price_id' => 'price_123',
            'sort_order' => 15,
        ], $override);
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.meeting-packs.create'));

        $response->assertOk();
        $response->assertViewIs('meeting-pack.management.create');
    }

    public function test_admin_can_create_meeting_pack_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload());

        $plan = MeetingPack::query()->where('name', '7 回パック')->firstOrFail();
        $response->assertRedirect(route('admin.meeting-packs.show', $plan));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('meeting_packs', [
            'id' => $plan->id,
            'meeting_count' => 7,
            'price' => 18000,
            'stripe_price_id' => 'price_123',
            'sort_order' => 15,
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_status_in_payload_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload(['status' => 'published']));

        $this->assertDatabaseHas('meeting_packs', ['name' => '7 回パック', 'status' => 'draft']);
    }

    public function test_optional_fields_can_be_omitted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), $this->payload([
                'description' => null,
                'stripe_price_id' => null,
                'sort_order' => null,
            ]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('meeting_packs', [
            'name' => '7 回パック',
            'description' => null,
            'stripe_price_id' => null,
            'sort_order' => 0,
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), $this->payload(['meeting_count' => '']))
            ->assertSessionHasErrors('meeting_count');

        $this->actingAs($admin)
            ->post(route('admin.meeting-packs.store'), $this->payload(['price' => '']))
            ->assertSessionHasErrors('price');

        $this->assertDatabaseCount('meeting_packs', 0);
    }

    public function test_coach_and_student_cannot_create(): void
    {
        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.meeting-packs.create'))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.meeting-packs.store'), $this->payload())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('meeting_packs', 0);
    }
}

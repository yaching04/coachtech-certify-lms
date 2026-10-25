<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
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
            'name' => '新規テストプラン',
            'description' => '説明文',
            'duration_days' => 60,
            'default_meeting_quota' => 7,
            'sort_order' => 15,
        ], $override);
    }

    public function test_admin_can_view_create_form(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.create'));

        $response->assertOk();
        $response->assertViewIs('plan.management.create');
    }

    public function test_admin_can_create_plan_as_draft(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload());

        $plan = Plan::query()->where('name', '新規テストプラン')->firstOrFail();
        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'duration_days' => 60,
            'default_meeting_quota' => 7,
            'sort_order' => 15,
            'status' => 'draft',
            'created_by_user_id' => $admin->id,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_status_in_payload_is_ignored(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.plans.store'), $this->payload(['status' => 'published']));

        $this->assertDatabaseHas('plans', ['name' => '新規テストプラン', 'status' => 'draft']);
    }

    public function test_optional_fields_can_be_omitted(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload([
                'description' => null,
                'sort_order' => null,
            ]))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('plans', [
            'name' => '新規テストプラン',
            'description' => null,
            'sort_order' => 0,
        ]);
    }

    public function test_required_fields_are_validated(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload(['duration_days' => '']))
            ->assertSessionHasErrors('duration_days');

        $this->actingAs($admin)
            ->post(route('admin.plans.store'), $this->payload(['default_meeting_quota' => '']))
            ->assertSessionHasErrors('default_meeting_quota');

        $this->assertDatabaseCount('plans', 0);
    }

    public function test_coach_and_student_cannot_create(): void
    {
        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.plans.create'))
                ->assertForbidden();

            $this->actingAs($user)
                ->post(route('admin.plans.store'), $this->payload())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('plans', 0);
    }
}

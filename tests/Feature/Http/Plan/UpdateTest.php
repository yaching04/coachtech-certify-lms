<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
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
            'name' => '更新後プラン',
            'description' => '更新後の説明',
            'duration_days' => 120,
            'default_meeting_quota' => 8,
            'sort_order' => 5,
        ], $override);
    }

    public function test_admin_can_view_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();

        $response = $this->actingAs($admin)->get(route('admin.plans.edit', $plan));

        $response->assertOk();
        $response->assertViewIs('plan.management.edit');
    }

    public function test_admin_can_update_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create();

        $response = $this->actingAs($admin)->put(route('admin.plans.update', $plan), $this->payload());

        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id,
            'name' => '更新後プラン',
            'duration_days' => 120,
            'default_meeting_quota' => 8,
            'sort_order' => 5,
            'updated_by_user_id' => $admin->id,
        ]);
    }

    public function test_status_is_not_changed_by_update(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create();

        $this->actingAs($admin)->put(route('admin.plans.update', $plan), $this->payload(['status' => 'published']));

        $this->assertSame('draft', $plan->fresh()->status->value);
    }

    public function test_validation_errors_keep_record_unchanged(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create(['name' => '元の名前']);

        $this->actingAs($admin)
            ->put(route('admin.plans.update', $plan), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->assertSame('元の名前', $plan->fresh()->name);
    }

    public function test_coach_and_student_cannot_update(): void
    {
        $plan = Plan::factory()->create(['name' => '元の名前']);

        foreach ([User::factory()->coach()->create(), User::factory()->student()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.plans.edit', $plan))
                ->assertForbidden();

            $this->actingAs($user)
                ->put(route('admin.plans.update', $plan), $this->payload())
                ->assertForbidden();
        }

        $this->assertSame('元の名前', $plan->fresh()->name);
    }
}
